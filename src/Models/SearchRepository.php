<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/**
 * Indice di ricerca. Usa SQLite FTS5 se disponibile, altrimenti LIKE.
 * I testi sono già normalizzati (minuscolo, senza accenti) da SearchIndexer.
 */
final class SearchRepository extends Repository
{
    public function fts(): bool
    {
        return $this->db->hasFts5() && (bool) $this->db->value("SELECT 1 FROM sqlite_master WHERE name = 'search_fts'");
    }

    public function put(string $entityType, string $entityId, string $title, string $keywords, string $body): void
    {
        $this->db->upsert('search_docs', [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'title'       => $title,
            'keywords'    => $keywords,
            'body'        => $body,
        ], ['entity_type', 'entity_id']);

        if ($this->fts()) {
            $key = $entityType . ':' . $entityId;
            $this->db->run('DELETE FROM search_fts WHERE entity_key = ?', [$key]);
            $this->db->run('INSERT INTO search_fts (entity_key, title, keywords, body) VALUES (?, ?, ?, ?)', [$key, $title, $keywords, $body]);
        }
    }

    public function clear(): void
    {
        $this->db->exec('DELETE FROM search_docs');
        if ($this->fts()) {
            $this->db->exec('DELETE FROM search_fts');
        }
    }

    /**
     * @param array<int, string[]> $stemGroups un gruppo di radici per concetto
     * @param bool $all true = tutti i concetti devono comparire (AND), false = almeno uno (OR)
     * @return array<int, array{entity_key:string, title:string, keywords:string, body:string, score:float}>
     */
    public function query(array $stemGroups, bool $all, int $limit = 200): array
    {
        $stemGroups = array_values(array_filter($stemGroups));
        if (!$stemGroups) {
            return [];
        }
        return $this->fts() ? $this->queryFts($stemGroups, $all, $limit) : $this->queryLike($stemGroups, $all, $limit);
    }

    private function queryFts(array $groups, bool $all, int $limit): array
    {
        $parts = array_map(
            static fn (array $stems) => '(' . implode(' OR ', array_map(static fn ($s) => '"' . str_replace('"', '', $s) . '"*', $stems)) . ')',
            $groups
        );
        $match = implode($all ? ' AND ' : ' OR ', $parts);
        // pesi bm25: entity_key (ignorata), titolo, parole chiave, contenuti
        return $this->db->all(
            'SELECT entity_key, title, keywords, body, -bm25(search_fts, 0.0, 10.0, 5.0, 1.0) AS score
               FROM search_fts WHERE search_fts MATCH ? ORDER BY score DESC LIMIT ' . (int) $limit,
            [$match]
        );
    }

    private function queryLike(array $groups, bool $all, int $limit): array
    {
        $clauses = [];
        $params  = [];
        foreach ($groups as $stems) {
            $or = [];
            foreach ($stems as $s) {
                $or[] = '(title LIKE ? OR keywords LIKE ? OR body LIKE ?)';
                array_push($params, "%$s%", "%$s%", "%$s%");
            }
            $clauses[] = '(' . implode(' OR ', $or) . ')';
        }
        $rows = $this->db->all(
            "SELECT entity_type || ':' || entity_id AS entity_key, title, keywords, body, 0 AS score
               FROM search_docs WHERE " . implode($all ? ' AND ' : ' OR ', $clauses) . ' LIMIT ' . (int) $limit,
            $params
        );
        foreach ($rows as &$r) {
            foreach (array_merge(...$groups) as $s) {
                $r['score'] += 10 * substr_count($r['title'], $s) + 5 * substr_count($r['keywords'], $s) + substr_count($r['body'], $s);
            }
        }
        usort($rows, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return $rows;
    }

    public static function createFtsTable(Database $db): bool
    {
        if (!$db->hasFts5()) {
            return false;
        }
        $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_fts USING fts5(entity_key UNINDEXED, title, keywords, body, tokenize = 'unicode61')");
        return true;
    }
}
