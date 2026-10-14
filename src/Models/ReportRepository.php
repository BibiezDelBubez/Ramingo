<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class ReportRepository extends Repository
{
    public function upsertFromScan(array $r, string $workspaceId, string $webUrl, string $now): void
    {
        $this->db->upsert('reports', [
            'id'                   => $r['id'],
            'workspace_id'         => $workspaceId,
            'dataset_id'           => $r['datasetId'] ?? null,
            'name'                 => $r['name'] ?? '(senza nome)',
            'description'          => $r['description'] ?? null,
            'report_type'          => $r['reportType'] ?? 'PowerBIReport',
            'web_url'              => $webUrl,
            'created_by'           => $r['createdBy'] ?? null,
            'modified_by'          => $r['modifiedBy'] ?? null,
            'created_at'           => $r['createdDateTime'] ?? null,
            'modified_at'          => $r['modifiedDateTime'] ?? null,
            'endorsement'          => $r['endorsementDetails']['endorsement'] ?? null,
            'certified_by'         => $r['endorsementDetails']['certifiedBy'] ?? null,
            'sensitivity_label_id' => $r['sensitivityLabel']['labelId'] ?? null,
            'raw_json'             => self::json(array_diff_key($r, ['users' => 1])),
            'first_seen_at'        => $now,
            'last_seen_at'         => $now,
            'is_deleted'           => 0,
        ], ['id'], ['first_seen_at']);
    }

    public function setAppLink(string $reportId, string $appId, string $appUrl): void
    {
        $this->db->run('UPDATE reports SET app_id = ?, app_url = ? WHERE id = ?', [$appId, $appUrl, $reportId]);
    }

    public function exists(string $id): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM reports WHERE id = ?', [$id]);
    }

    public function markMissingInWorkspace(string $workspaceId, string $runStart): int
    {
        return $this->markMissing('reports', $workspaceId, $runStart);
    }

    /** Scheda completa: report + workspace + area + modello semantico. */
    public function find(string $id): ?array
    {
        return $this->db->one(
            'SELECT r.*, w.name AS workspace_name, w.description AS workspace_description,
                    a.id AS area_id, a.name AS area_name, ea.assigned_on AS area_assigned_on,
                    d.name AS dataset_name, d.description AS dataset_description, d.configured_by AS dataset_configured_by,
                    d.target_storage_mode AS dataset_storage_mode, d.workspace_id AS dataset_workspace_id,
                    dr.last_success_at AS data_refreshed_at, dr.last_attempt_status AS data_last_status,
                    dr.last_attempt_at AS data_last_attempt_at, dr.has_history AS data_has_history,
                    dr.accessible AS data_accessible, dr.checked_at AS data_checked_at
               FROM reports r
               JOIN workspaces w ON w.id = r.workspace_id
          LEFT JOIN item_area ea ON ea.entity_type = \'report\' AND ea.entity_id = r.id
          LEFT JOIN areas a ON a.id = ea.area_id
          LEFT JOIN datasets d ON d.id = r.dataset_id
          LEFT JOIN dataset_refreshes dr ON dr.dataset_id = r.dataset_id
              WHERE r.id = ?',
            [$id]
        );
    }

    /**
     * Pagine del report. Se la definizione è nota si usano quelle (sono collegate ai grafici e più complete);
     * altrimenti l'elenco letto dall'API Pages di Power BI. Così una pagina non compare mai due volte.
     */
    public function pages(string $reportId): array
    {
        $rows = $this->db->all('SELECT * FROM report_pages WHERE report_id = ? ORDER BY ordinal, display_name', [$reportId]);
        $def = array_values(array_filter($rows, static fn ($p) => $p['source'] === 'def'));
        return $def ?: $rows;
    }

    /** @return array<string, array> visual raggruppati per nome pagina */
    public function visualsByPage(string $reportId): array
    {
        $out = [];
        foreach ($this->db->all('SELECT * FROM report_visuals WHERE report_id = ? ORDER BY id', [$reportId]) as $v) {
            $v['fields'] = array_map(
                [\Ramingo\Services\Catalog\ReportUsage::class, 'normalizeField'],
                json_decode((string) $v['fields_json'], true) ?: []
            );
            $out[$v['page_name']][] = $v;
        }
        return $out;
    }

    public function replacePages(string $reportId, array $pages, string $source): void
    {
        $this->db->run('DELETE FROM report_pages WHERE report_id = ? AND source = ?', [$reportId, $source]);
        foreach ($pages as $i => $p) {
            $row = [
                'report_id'    => $reportId,
                'name'         => $p['name'],
                'display_name' => $p['displayName'],
                'ordinal'      => (int) ($p['order'] ?? $i),
                'source'       => $source,
            ];
            if ($source === 'def') {
                $this->db->upsert('report_pages', $row, ['report_id', 'name']); // la definizione è più completa
            } else {
                $this->db->run( // l'elenco dell'API non sovrascrive pagine già lette dalla definizione
                    'INSERT OR IGNORE INTO report_pages (report_id, name, display_name, ordinal, source) VALUES (:report_id, :name, :display_name, :ordinal, :source)',
                    $row
                );
            }
        }
    }

    public function replaceVisuals(string $reportId, array $visuals): void
    {
        $this->db->run('DELETE FROM report_visuals WHERE report_id = ?', [$reportId]);
        foreach ($visuals as $v) {
            $this->db->run(
                'INSERT INTO report_visuals (report_id, page_name, visual_type, title, fields_json) VALUES (?, ?, ?, ?, ?)',
                [$reportId, $v['page'], $v['type'], $v['title'], self::json($v['fields'])]
            );
        }
    }

    /** Report per cui vale la pena (ri)chiedere le pagine a Power BI. */
    public function needingPages(): array
    {
        return $this->db->all(
            "SELECT id, workspace_id FROM reports
              WHERE is_deleted = 0 AND report_type = 'PowerBIReport'
                AND (pages_fetched_at IS NULL OR (modified_at IS NOT NULL AND modified_at > pages_fetched_at))"
        );
    }

    /** Report di cui (ri)leggere la definizione: mai letta, oppure modificati dopo l'ultima lettura. */
    public function needingDefinition(): array
    {
        return $this->db->all(
            "SELECT id, workspace_id FROM reports
              WHERE is_deleted = 0 AND report_type = 'PowerBIReport'
                AND (definition_fetched_at IS NULL OR (modified_at IS NOT NULL AND modified_at > definition_fetched_at))"
        );
    }

    public function touchDefinitionFetched(string $reportId, string $now): void
    {
        $this->db->run('UPDATE reports SET definition_fetched_at = ? WHERE id = ?', [$now, $reportId]);
    }

    /** Salva pagine e visual letti dalla definizione del report. */
    public function saveDefinition(string $reportId, array $definition): void
    {
        $this->db->transaction(function () use ($reportId, $definition): void {
            $this->replacePages($reportId, $definition['pages'], 'def');
            $this->replaceVisuals($reportId, $definition['visuals']);
        });
    }

    public function touchPagesFetched(string $reportId, string $now): void
    {
        $this->db->run('UPDATE reports SET pages_fetched_at = ? WHERE id = ?', [$now, $reportId]);
    }

    public function activeIds(): array
    {
        return array_column($this->db->all('SELECT id FROM reports WHERE is_deleted = 0'), 'id');
    }

    /** Altri report che usano lo stesso modello semantico. */
    public function siblingsByDataset(string $datasetId, string $excludeId): array
    {
        return $this->db->all(
            'SELECT r.id, r.name, w.name AS workspace_name FROM reports r JOIN workspaces w ON w.id = r.workspace_id
              WHERE r.dataset_id = ? AND r.id <> ? AND r.is_deleted = 0 ORDER BY r.name COLLATE NOCASE LIMIT 20',
            [$datasetId, $excludeId]
        );
    }
}
