<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class DashboardRepository extends Repository
{
    public function upsertFromScan(array $d, string $workspaceId, string $webUrl, string $now): void
    {
        $this->db->upsert('dashboards', [
            'id'                   => $d['id'],
            'workspace_id'         => $workspaceId,
            'name'                 => $d['displayName'] ?? '(senza nome)',
            'web_url'              => $webUrl,
            'is_read_only'         => (int) ($d['isReadOnly'] ?? false),
            'sensitivity_label_id' => $d['sensitivityLabel']['labelId'] ?? null,
            'raw_json'             => self::json(array_diff_key($d, array_flip(['tiles', 'users']))),
            'first_seen_at'        => $now,
            'last_seen_at'         => $now,
            'is_deleted'           => 0,
        ], ['id'], ['first_seen_at']);

        $this->db->run('DELETE FROM dashboard_tiles WHERE dashboard_id = ?', [$d['id']]);
        foreach ($d['tiles'] ?? [] as $t) {
            $this->db->run(
                'INSERT INTO dashboard_tiles (dashboard_id, id, title, report_id, dataset_id) VALUES (?, ?, ?, ?, ?)',
                [$d['id'], $t['id'], $t['title'] ?? null, $t['reportId'] ?? null, $t['datasetId'] ?? null]
            );
        }
    }

    public function setAppLink(string $dashboardId, string $appId, string $appUrl): void
    {
        $this->db->run('UPDATE dashboards SET app_id = ?, app_url = ? WHERE id = ?', [$appId, $appUrl, $dashboardId]);
    }

    public function exists(string $id): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM dashboards WHERE id = ?', [$id]);
    }

    public function markMissingInWorkspace(string $workspaceId, string $runStart): int
    {
        return $this->markMissing('dashboards', $workspaceId, $runStart);
    }

    public function find(string $id): ?array
    {
        return $this->db->one(
            'SELECT d.*, w.name AS workspace_name, w.description AS workspace_description,
                    a.id AS area_id, a.name AS area_name, ea.assigned_on AS area_assigned_on
               FROM dashboards d
               JOIN workspaces w ON w.id = d.workspace_id
          LEFT JOIN item_area ea ON ea.entity_type = \'dashboard\' AND ea.entity_id = d.id
          LEFT JOIN areas a ON a.id = ea.area_id
              WHERE d.id = ?',
            [$id]
        );
    }

    /** Riquadri con il report di origine (se catalogato). */
    public function tiles(string $dashboardId): array
    {
        return $this->db->all(
            'SELECT t.*, r.name AS report_name FROM dashboard_tiles t
          LEFT JOIN reports r ON r.id = t.report_id AND r.is_deleted = 0
              WHERE t.dashboard_id = ? ORDER BY t.title COLLATE NOCASE',
            [$dashboardId]
        );
    }
}
