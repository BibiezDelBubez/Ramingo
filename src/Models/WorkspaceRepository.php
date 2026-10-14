<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class WorkspaceRepository extends Repository
{
    public function upsertFromScan(array $ws, string $now): void
    {
        $this->db->upsert('workspaces', [
            'id'                       => $ws['id'],
            'name'                     => $ws['name'] ?? '(senza nome)',
            'description'              => $ws['description'] ?? null,
            'type'                     => $ws['type'] ?? null,
            'state'                    => $ws['state'] ?? null,
            'capacity_id'              => $ws['capacityId'] ?? null,
            'is_on_dedicated_capacity' => (int) ($ws['isOnDedicatedCapacity'] ?? false),
            'raw_json'                 => self::json(array_diff_key($ws, array_flip(['reports', 'datasets', 'dashboards', 'dataflows', 'users']))),
            'first_seen_at'            => $now,
            'last_seen_at'             => $now,
            'is_deleted'               => 0,
        ], ['id'], ['first_seen_at']);
    }

    public function markDeletedNotSeenSince(string $runStart): int
    {
        return $this->db->run('UPDATE workspaces SET is_deleted = 1 WHERE last_seen_at < ? AND is_deleted = 0', [$runStart])->rowCount();
    }

    public function find(string $id): ?array
    {
        return $this->db->one(
            'SELECT w.*, a.id AS area_id, a.name AS area_name
               FROM workspaces w
          LEFT JOIN workspace_areas wa ON wa.workspace_id = w.id
          LEFT JOIN areas a ON a.id = wa.area_id
              WHERE w.id = ?',
            [$id]
        );
    }

    /** Workspace attivi con area assegnata e numero di report. */
    public function allWithAreas(): array
    {
        return $this->db->all(
            'SELECT w.id, w.name, w.type, a.id AS area_id, a.name AS area_name,
                    (SELECT COUNT(*) FROM reports r WHERE r.workspace_id = w.id AND r.is_deleted = 0) AS report_count
               FROM workspaces w
          LEFT JOIN workspace_areas wa ON wa.workspace_id = w.id
          LEFT JOIN areas a ON a.id = wa.area_id
              WHERE w.is_deleted = 0 AND COALESCE(w.type, \'\') <> \'AdminWorkspace\'
           ORDER BY w.name COLLATE NOCASE'
        );
    }
}
