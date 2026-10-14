<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class DatasetRepository extends Repository
{
    public function upsertFromScan(array $d, string $workspaceId, string $now): void
    {
        $this->db->upsert('datasets', [
            'id'                    => $d['id'],
            'workspace_id'          => $workspaceId,
            'name'                  => $d['name'] ?? '(senza nome)',
            'description'           => $d['description'] ?? null,
            'configured_by'         => $d['configuredBy'] ?? null,
            'created_at'            => $d['createdDate'] ?? null,
            'target_storage_mode'   => $d['targetStorageMode'] ?? null,
            'content_provider_type' => $d['contentProviderType'] ?? null,
            'endorsement'           => $d['endorsementDetails']['endorsement'] ?? null,
            'certified_by'          => $d['endorsementDetails']['certifiedBy'] ?? null,
            'sensitivity_label_id'  => $d['sensitivityLabel']['labelId'] ?? null,
            'raw_json'              => self::json(array_diff_key($d, array_flip(['tables', 'users', 'expressions']))),
            'first_seen_at'         => $now,
            'last_seen_at'          => $now,
            'is_deleted'            => 0,
        ], ['id'], ['first_seen_at']);
    }

    public function markMissingInWorkspace(string $workspaceId, string $runStart): int
    {
        return $this->markMissing('datasets', $workspaceId, $runStart);
    }

    /** Sostituisce lo schema tecnico (tabelle/colonne/misure) del modello. */
    public function replaceSchema(string $datasetId, array $tables): void
    {
        $this->db->run('DELETE FROM dataset_tables WHERE dataset_id = ?', [$datasetId]);
        foreach ($tables as $t) {
            $this->db->run(
                'INSERT INTO dataset_tables (dataset_id, name, description, is_hidden, source_expression) VALUES (?, ?, ?, ?, ?)',
                [$datasetId, $t['name'], $t['description'] ?? null, (int) ($t['isHidden'] ?? false), $t['source'][0]['expression'] ?? null]
            );
            $tableId = (int) $this->db->lastInsertId();
            foreach ($t['columns'] ?? [] as $c) {
                $this->db->run(
                    'INSERT INTO dataset_columns (table_id, name, data_type, column_type, description, is_hidden, expression) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$tableId, $c['name'], $c['dataType'] ?? null, $c['columnType'] ?? null, $c['description'] ?? null, (int) ($c['isHidden'] ?? false), $c['expression'] ?? null]
                );
            }
            foreach ($t['measures'] ?? [] as $m) {
                $this->db->run(
                    'INSERT INTO dataset_measures (table_id, name, description, is_hidden, expression, format_string) VALUES (?, ?, ?, ?, ?, ?)',
                    [$tableId, $m['name'], $m['description'] ?? null, (int) ($m['isHidden'] ?? false), $m['expression'] ?? null, $m['formatString'] ?? null]
                );
            }
        }
    }

    public function replaceDatasources(string $datasetId, array $instances): void
    {
        $this->db->run('DELETE FROM datasources WHERE dataset_id = ?', [$datasetId]);
        foreach ($instances as $ds) {
            $this->db->run(
                'INSERT INTO datasources (dataset_id, datasource_type, connection_details, gateway_id) VALUES (?, ?, ?, ?)',
                [$datasetId, $ds['datasourceType'] ?? null, self::json($ds['connectionDetails'] ?? []), $ds['gatewayId'] ?? null]
            );
        }
    }

    /**
     * Schema del modello: tabelle con colonne e misure annidate.
     * @return array<int, array{name:string, is_hidden:int, columns:array, measures:array}>
     */
    public function schema(string $datasetId): array
    {
        $tables = $this->db->all('SELECT * FROM dataset_tables WHERE dataset_id = ? ORDER BY is_hidden, name COLLATE NOCASE', [$datasetId]);
        if (!$tables) {
            return [];
        }
        $byId = [];
        foreach ($tables as $t) {
            $byId[$t['id']] = $t + ['columns' => [], 'measures' => []];
        }
        $in = \Ramingo\Core\Database::placeholders(array_keys($byId));
        foreach ($this->db->all("SELECT * FROM dataset_columns WHERE table_id IN ($in) ORDER BY name COLLATE NOCASE", array_keys($byId)) as $c) {
            $byId[$c['table_id']]['columns'][] = $c;
        }
        foreach ($this->db->all("SELECT * FROM dataset_measures WHERE table_id IN ($in) ORDER BY name COLLATE NOCASE", array_keys($byId)) as $m) {
            $byId[$m['table_id']]['measures'][] = $m;
        }
        return array_values($byId);
    }

    /** Misure visibili del modello (usate per KPI automatici e ricerca). */
    public function visibleMeasures(string $datasetId): array
    {
        return $this->db->all(
            'SELECT m.name, m.description, m.format_string, t.name AS table_name
               FROM dataset_measures m JOIN dataset_tables t ON t.id = m.table_id
              WHERE t.dataset_id = ? AND m.is_hidden = 0
           ORDER BY m.name COLLATE NOCASE',
            [$datasetId]
        );
    }

    public function datasources(string $datasetId): array
    {
        $rows = $this->db->all('SELECT * FROM datasources WHERE dataset_id = ?', [$datasetId]);
        foreach ($rows as &$r) {
            $r['details'] = json_decode((string) $r['connection_details'], true) ?: [];
        }
        return $rows;
    }
}
