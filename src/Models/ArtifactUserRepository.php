<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/** Permessi letti da Power BI (Scanner API, getArtifactUsers). */
final class ArtifactUserRepository extends Repository
{
    /** Chiave nel JSON della Scanner API che contiene il diritto di accesso, per tipo. */
    private const RIGHT_KEY = [
        'workspace' => 'groupUserAccessRight',
        'report'    => 'reportUserAccessRight',
        'dashboard' => 'dashboardUserAccessRight',
        'dataset'   => 'datasetUserAccessRight',
    ];

    public function replace(string $type, string $id, array $users, bool $append = false): void
    {
        if (!$append) {
            $this->db->run('DELETE FROM artifact_users WHERE artifact_type = ? AND artifact_id = ?', [$type, $id]);
        }
        foreach ($users as $u) {
            $graphId = $u['graphId'] ?? $u['identifier'] ?? null;
            $right   = $u[self::RIGHT_KEY[$type]] ?? null;
            if (!$graphId || !$right) {
                continue;
            }
            $this->db->upsert('artifact_users', [
                'artifact_type'  => $type,
                'artifact_id'    => $id,
                'graph_id'       => $graphId,
                'display_name'   => $u['displayName'] ?? null,
                'email'          => $u['emailAddress'] ?? null,
                'principal_type' => $u['principalType'] ?? null,
                'access_right'   => $right,
            ], ['artifact_type', 'artifact_id', 'graph_id', 'access_right']);
        }
    }

    /** Proprietari / amministratori di un oggetto (per la voce "Proprietario"). */
    public function owners(string $type, string $id): array
    {
        return $this->db->all(
            "SELECT DISTINCT display_name, email, principal_type, access_right FROM artifact_users
              WHERE artifact_type = ? AND artifact_id = ? AND access_right IN ('Owner','Admin','ReadWriteReshareExplore')
           ORDER BY display_name COLLATE NOCASE",
            [$type, $id]
        );
    }

    /**
     * Fra gli oggetti indicati, quali sono condivisi con almeno una delle identità?
     * Considera sia i permessi diretti sia l'appartenenza al workspace.
     * @param array<int, array{type:string,id:string,workspace_id:string}> $items
     * @return array<string, true> chiave "type:id"
     */
    public function grantedAmong(array $items, array $principalIds): array
    {
        if (!$items || !$principalIds) {
            return [];
        }
        $in = Database::placeholders($principalIds);
        $rows = $this->db->all(
            "SELECT artifact_type, artifact_id FROM artifact_users WHERE graph_id IN ($in)",
            $principalIds
        );
        $granted = [];
        foreach ($rows as $r) {
            $granted[$r['artifact_type'] . ':' . $r['artifact_id']] = true;
        }
        $out = [];
        foreach ($items as $it) {
            if (isset($granted[$it['type'] . ':' . $it['id']]) || isset($granted['workspace:' . $it['workspace_id']])) {
                $out[$it['type'] . ':' . $it['id']] = true;
            }
        }
        return $out;
    }
}
