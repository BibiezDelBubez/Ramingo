<?php
declare(strict_types=1);

namespace Ramingo\Services\Search;

use Ramingo\Core\Database;
use Ramingo\Models\SearchRepository;

/**
 * Costruisce i documenti di ricerca combinando TUTTE le fonti:
 * metadati Power BI, definizioni tecniche, testi manuali e interpretazioni automatiche.
 * L'indice è derivato: si può ricostruire in ogni momento (bin/reindex.php).
 */
final class SearchIndexer
{
    public function __construct(
        private readonly Database $db,
        private readonly SearchRepository $index,
        private readonly \Ramingo\Models\GlossaryRepository $glossary,
    ) {
    }

    public function rebuildAll(): int
    {
        return (int) $this->db->transaction(function (): int {
            $this->index->clear();
            $n = 0;
            foreach ($this->db->all('SELECT id FROM reports WHERE is_deleted = 0') as $r) {
                $this->indexReport($r['id']);
                $n++;
            }
            foreach ($this->db->all('SELECT id FROM dashboards WHERE is_deleted = 0') as $d) {
                $this->indexDashboard($d['id']);
                $n++;
            }
            return $n;
        });
    }

    public function indexReport(string $id): void
    {
        $r = $this->db->one(
            "SELECT r.name, r.description, r.dataset_id, w.name AS ws, w.description AS ws_description, a.name AS area, pa.name AS parent_area,
                    d.description AS dataset_description
               FROM reports r JOIN workspaces w ON w.id = r.workspace_id
          LEFT JOIN item_area ea ON ea.entity_type = 'report' AND ea.entity_id = r.id
          LEFT JOIN areas a ON a.id = ea.area_id LEFT JOIN areas pa ON pa.id = a.parent_id
          LEFT JOIN datasets d ON d.id = r.dataset_id
              WHERE r.id = ?",
            [$id]
        );
        if (!$r) {
            return;
        }
        $keywords = [$r['ws'], $r['area'], $r['parent_area'], ...$this->tags('report', $id), ...$this->annotations('report', $id, ['kpi', 'audience'])];
        $body = [
            $r['description'],
            $r['dataset_description'],
            $r['ws_description'],
            ...$this->annotations('report', $id, ['description', 'summary', 'notes', 'ai_description']),
            ...$this->column('SELECT display_name FROM report_pages WHERE report_id = ?', [$id]),
            ...$this->column('SELECT title FROM report_visuals WHERE report_id = ? AND title IS NOT NULL', [$id]),
        ];
        if ($r['dataset_id']) {
            // Con un modello condiviso (es. «Master») si indicizza solo ciò che QUESTO report usa davvero:
            // altrimenti cercando «turnover» uscirebbero tutti i report costruiti sullo stesso modello.
            $usage = \Ramingo\Services\Catalog\ReportUsage::fromRows(
                $this->db->all('SELECT fields_json FROM report_visuals WHERE report_id = ?', [$id])
            );
            $tables = array_filter(
                $this->db->all('SELECT name, description FROM dataset_tables WHERE dataset_id = ? AND is_hidden = 0', [$r['dataset_id']]),
                static fn ($t) => $usage->usesTable($t['name'])
            );
            $names = [
                'table'   => array_column($tables, 'name'),
                'measure' => array_values(array_filter(
                    $this->column('SELECT m.name FROM dataset_measures m JOIN dataset_tables t ON t.id = m.table_id WHERE t.dataset_id = ? AND m.is_hidden = 0', [$r['dataset_id']]),
                    static fn ($n) => $usage->usesMeasure((string) $n)
                )),
                'column'  => array_column(array_filter(
                    $this->db->all('SELECT t.name AS t, c.name AS c FROM dataset_columns c JOIN dataset_tables t ON t.id = c.table_id WHERE t.dataset_id = ? AND c.is_hidden = 0 AND t.is_hidden = 0', [$r['dataset_id']]),
                    static fn ($x) => $usage->usesColumn($x['t'], $x['c'])
                ), 'c'),
            ];
            $body = array_merge($body, ...array_values($names));
            $body = array_merge($body, array_column($tables, 'description'));
            // nomi comprensibili e descrizioni del glossario: si trova "vendite al dettaglio" anche se la tabella si chiama VaGazTrrr
            foreach ($names as $kind => $list) {
                foreach ($list as $name) {
                    $g = $this->glossary->find($kind, (string) $name);
                    if ($g) {
                        $keywords[] = $g['label'];
                        $body[] = $g['description'];
                    }
                }
            }
        }
        $this->put('report', $id, $r['name'], $keywords, $body);
    }

    public function indexDashboard(string $id): void
    {
        $d = $this->db->one(
            "SELECT d.name, w.name AS ws, a.name AS area, pa.name AS parent_area FROM dashboards d JOIN workspaces w ON w.id = d.workspace_id
          LEFT JOIN item_area ea ON ea.entity_type = 'dashboard' AND ea.entity_id = d.id
          LEFT JOIN areas a ON a.id = ea.area_id LEFT JOIN areas pa ON pa.id = a.parent_id WHERE d.id = ?",
            [$id]
        );
        if (!$d) {
            return;
        }
        $keywords = [$d['ws'], $d['area'], $d['parent_area'], ...$this->tags('dashboard', $id), ...$this->annotations('dashboard', $id, ['kpi', 'audience'])];
        $body = [
            ...$this->annotations('dashboard', $id, ['description', 'summary', 'notes', 'ai_description']),
            ...$this->column('SELECT title FROM dashboard_tiles WHERE dashboard_id = ? AND title IS NOT NULL', [$id]),
        ];
        $this->put('dashboard', $id, $d['name'], $keywords, $body);
    }

    private function put(string $type, string $id, string $title, array $keywords, array $body): void
    {
        $join = static fn (array $parts) => Text::normalize(implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== '')));
        // I nomi tecnici tipo "TotaleVendite" o "Sales_Region" vengono spezzati in parole.
        $split = static fn (array $parts) => array_map(
            static fn ($p) => $p === null ? null : preg_replace(['~([a-z])([A-Z])~', '~[_\.\-]+~'], ['$1 $2', ' '], (string) $p),
            $parts
        );
        $this->index->put($type, $id, $join($split([$title])), $join($split($keywords)), $join($split($body)));
    }

    private function column(string $sql, array $params): array
    {
        return $this->db->column($sql, $params);
    }

    private function tags(string $type, string $id): array
    {
        return $this->column('SELECT tag FROM tags WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
    }

    private function annotations(string $type, string $id, array $fields): array
    {
        $in = Database::placeholders($fields);
        return $this->column(
            "SELECT value FROM annotations WHERE entity_type = ? AND entity_id = ? AND is_current = 1 AND field IN ($in)",
            [$type, $id, ...$fields]
        );
    }
}
