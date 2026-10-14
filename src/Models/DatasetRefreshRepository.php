<?php
declare(strict_types=1);

namespace Ramingo\Models;

/** Ultimo aggiornamento dei dati dei modelli semantici (vedi tabella dataset_refreshes). */
final class DatasetRefreshRepository extends Repository
{
    /**
     * Modelli usati dai report del catalogo e da ricontrollare (mai controllati o controllati
     * da più di $minutes minuti). Il workspace è quello del MODELLO, che può essere diverso
     * da quello del report (report collegati a un modello condiviso).
     * @return array<int, array{dataset_id:string, workspace_id:string}>
     */
    public function due(int $minutes): array
    {
        $limit = gmdate('Y-m-d\TH:i:s\Z', time() - $minutes * 60);
        return $this->db->all(
            "SELECT r.dataset_id,
                    MIN(COALESCE(d.workspace_id, json_extract(r.raw_json, '$.datasetWorkspaceId'), r.workspace_id)) AS workspace_id
               FROM reports r
          LEFT JOIN datasets d ON d.id = r.dataset_id
          LEFT JOIN dataset_refreshes dr ON dr.dataset_id = r.dataset_id
              WHERE r.is_deleted = 0 AND r.dataset_id IS NOT NULL AND r.dataset_id <> ''
                AND (dr.checked_at IS NULL OR dr.checked_at < ?)
           GROUP BY r.dataset_id",
            [$limit]
        );
    }

    /**
     * @param ?array $history tentativi dal più recente (status, endTime, startTime); null = non accessibile
     */
    public function save(string $datasetId, string $workspaceId, ?array $history, string $now): void
    {
        $row = [
            'dataset_id'          => $datasetId,
            'workspace_id'        => $workspaceId,
            'accessible'          => $history === null ? 0 : 1,
            'has_history'         => $history ? 1 : 0,
            'checked_at'          => $now,
            'last_attempt_at'     => null,
            'last_attempt_status' => null,
            'last_success_at'     => null,
        ];
        if ($history) {
            $last = $history[0];
            $row['last_attempt_at']     = $last['endTime'] ?? $last['startTime'] ?? null;
            $row['last_attempt_status'] = $last['status'] ?? null;
            foreach ($history as $h) {
                if (($h['status'] ?? '') === 'Completed' && !empty($h['endTime'])) {
                    $row['last_success_at'] = $h['endTime'];
                    break;
                }
            }
        }
        if ($history === null) {
            // non accessibile: si tiene l'ultima informazione nota, si aggiorna solo lo stato
            $this->db->run('UPDATE dataset_refreshes SET accessible = 0, checked_at = ? WHERE dataset_id = ?', [$now, $datasetId]);
            if ($this->db->value('SELECT 1 FROM dataset_refreshes WHERE dataset_id = ?', [$datasetId])) {
                return;
            }
        }
        $this->db->upsert('dataset_refreshes', $row, ['dataset_id']);
    }
}
