<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;
use Ramingo\Domain\Source;

final class TagRepository extends Repository
{
    /** Sostituisce i tag di una certa provenienza (manual o auto) per un oggetto. */
    public function replace(string $entityType, string $entityId, array $tags, Source $source): void
    {
        $this->db->run('DELETE FROM tags WHERE entity_type = ? AND entity_id = ? AND source = ?', [$entityType, $entityId, $source->value]);
        foreach (array_unique(array_filter(array_map(static fn ($t) => mb_strtolower(trim((string) $t)), $tags))) as $tag) {
            $this->db->run('INSERT OR IGNORE INTO tags (entity_type, entity_id, tag, source) VALUES (?, ?, ?, ?)', [$entityType, $entityId, $tag, $source->value]);
        }
    }

    /**
     * Tag scritti a mano già in uso, con quanti report/dashboard li usano.
     * Serve a riusare lo stesso tag invece di inventarne uno simile («lavaggi» / «lavaggio»).
     * @return array<int, array{tag:string, n:int}>
     */
    public function inUse(): array
    {
        return $this->db->all(
            "SELECT t.tag, COUNT(*) AS n FROM tags t
          LEFT JOIN reports r ON t.entity_type = 'report' AND r.id = t.entity_id
          LEFT JOIN dashboards d ON t.entity_type = 'dashboard' AND d.id = t.entity_id
              WHERE t.source = 'manual' AND COALESCE(r.is_deleted, d.is_deleted, 0) = 0
           GROUP BY t.tag ORDER BY n DESC, t.tag"
        );
    }

    /** @return array<string, array<int, array{tag:string, source:string}>> [entityId] => tags */
    public function forEntities(string $entityType, array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = Database::placeholders($ids);
        $out = [];
        foreach ($this->db->all("SELECT * FROM tags WHERE entity_type = ? AND entity_id IN ($in) ORDER BY source, tag", [$entityType, ...$ids]) as $r) {
            $out[$r['entity_id']][] = ['tag' => $r['tag'], 'source' => $r['source']];
        }
        return $out;
    }
}
