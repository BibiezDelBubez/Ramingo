<?php
declare(strict_types=1);

namespace Ramingo\Models;

/**
 * Glossario: nome comprensibile e descrizione per i nomi tecnici del modello dati.
 * Vale per NOME (senza distinzione maiuscole/minuscole) in tutti i modelli.
 */
final class GlossaryRepository extends Repository
{
    public const KINDS = ['table' => 'Tabelle', 'measure' => 'Misure', 'column' => 'Colonne'];

    private ?array $map = null;

    /** @return array<string, array<string, array{label:?string, description:?string, updated_by:?string, updated_at:string}>> [kind][termine minuscolo] */
    public function map(): array
    {
        if ($this->map === null) {
            $this->map = array_fill_keys(array_keys(self::KINDS), []);
            foreach ($this->db->all('SELECT * FROM glossary') as $g) {
                $this->map[$g['kind']][mb_strtolower($g['term'])] = $g;
            }
        }
        return $this->map;
    }

    public function find(string $kind, string $term): ?array
    {
        return $this->map()[$kind][mb_strtolower($term)] ?? null;
    }

    public function label(string $kind, string $term): ?string
    {
        $l = $this->find($kind, $term)['label'] ?? null;
        return $l !== null && $l !== '' ? $l : null;
    }

    /** Salva una voce; se nome e descrizione sono vuoti la voce viene tolta. */
    public function save(string $kind, string $term, string $label, string $description, string $by): void
    {
        $label = trim($label);
        $description = trim($description);
        if ($label === '' && $description === '') {
            $this->db->run('DELETE FROM glossary WHERE kind = ? AND term = ?', [$kind, $term]);
        } else {
            $this->db->upsert('glossary', [
                'kind'        => $kind,
                'term'        => $term,
                'label'       => $label !== '' ? mb_substr($label, 0, 120) : null,
                'description' => $description !== '' ? mb_substr($description, 0, 1000) : null,
                'updated_by'  => $by,
                'updated_at'  => now_iso(),
            ], ['kind', 'term']);
        }
        $this->map = null;
    }

    /**
     * Nomi tecnici presenti nei modelli, con uso e voce di glossario, dal più usato.
     * @return array{rows: array, total: int}
     */
    public function terms(string $kind, string $search, bool $onlyMissing, int $limit, int $offset): array
    {
        [$from, $nameCol, $extra] = match ($kind) {
            'table' => [
                'dataset_tables x JOIN datasets d ON d.id = x.dataset_id AND d.is_deleted = 0',
                'x.name', "MAX(x.description) AS pbi_description, '' AS context",
            ],
            'measure' => [
                'dataset_measures x JOIN dataset_tables t ON t.id = x.table_id JOIN datasets d ON d.id = t.dataset_id AND d.is_deleted = 0',
                'x.name', "MAX(x.description) AS pbi_description, GROUP_CONCAT(DISTINCT t.name) AS context",
            ],
            default => [
                'dataset_columns x JOIN dataset_tables t ON t.id = x.table_id AND t.is_hidden = 0 JOIN datasets d ON d.id = t.dataset_id AND d.is_deleted = 0',
                'x.name', "NULL AS pbi_description, GROUP_CONCAT(DISTINCT t.name) AS context",
            ],
        };
        $where = ['x.is_hidden = 0'];
        $having = [];
        $params = [];
        if ($search !== '') {
            $having[] = '(term LIKE ? OR g.label LIKE ? OR g.description LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        if ($onlyMissing) {
            $having[] = "(g.label IS NULL OR g.label = '')";
        }
        $sql = "SELECT $nameCol AS term, $extra,
                       COUNT(DISTINCT d.id) AS models, COUNT(DISTINCT r.id) AS reports,
                       g.label, g.description, g.updated_by, g.updated_at
                  FROM $from
             LEFT JOIN reports r ON r.dataset_id = d.id AND r.is_deleted = 0
             LEFT JOIN glossary g ON g.kind = '$kind' AND g.term = $nameCol COLLATE NOCASE
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY $nameCol COLLATE NOCASE"
             . ($having ? ' HAVING ' . implode(' AND ', $having) : '');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM ($sql)", $params);
        $rows = $this->db->all("SELECT * FROM ($sql) ORDER BY reports DESC, models DESC, term COLLATE NOCASE LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $params);
        return ['rows' => $rows, 'total' => $total];
    }

    /** Quante voci mancano per tipo (per i contatori delle schede). */
    public function missingCounts(): array
    {
        $out = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $out[$kind] = $this->terms($kind, '', true, 1, 0)['total'];
        }
        return $out;
    }
}
