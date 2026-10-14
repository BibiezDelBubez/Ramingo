<?php
declare(strict_types=1);

namespace Ramingo\Services\Sync;

use Ramingo\Core\Database;
use Ramingo\Models\ArtifactUserRepository;
use Ramingo\Models\DashboardRepository;
use Ramingo\Models\DatasetRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\WorkspaceRepository;
use Ramingo\Services\PowerBi\PowerBiUrls;

/**
 * Traduce il risultato della Scanner API nelle tabelle (1) Power BI e (2) definizioni tecniche.
 * Non tocca MAI annotazioni manuali, aree o tag manuali.
 */
final class ScanResultImporter
{
    public function __construct(
        private readonly Database $db,
        private readonly WorkspaceRepository $workspaces,
        private readonly DatasetRepository $datasets,
        private readonly ReportRepository $reports,
        private readonly DashboardRepository $dashboards,
        private readonly ArtifactUserRepository $users,
        private readonly PowerBiUrls $urls,
        private readonly array $syncCfg,
    ) {
    }

    /** @return array<string,int> statistiche */
    public function import(array $scan, string $runStart): array
    {
        $stats = ['workspaces' => 0, 'reports' => 0, 'dashboards' => 0, 'datasets' => 0, 'tables' => 0, 'measures' => 0, 'skipped_workspaces' => 0, 'deleted' => 0];

        // Mappa delle sorgenti dati condivise fra i dataset
        $instances = [];
        foreach ($scan['datasourceInstances'] ?? [] as $ds) {
            $instances[$ds['datasourceId']] = $ds;
        }

        foreach ($scan['workspaces'] ?? [] as $ws) {
            if ($this->skip($ws)) {
                $stats['skipped_workspaces']++;
                continue;
            }
            $this->db->transaction(function () use ($ws, $instances, $runStart, &$stats): void {
                $this->importWorkspace($ws, $instances, $runStart, $stats);
            });
        }
        return $stats;
    }

    private function skip(array $ws): bool
    {
        if (($this->syncCfg['exclude_personal_workspaces'] ?? true) && in_array($ws['type'] ?? '', ['PersonalGroup', 'Personal'], true)) {
            return true;
        }
        return in_array($ws['id'], (array) ($this->syncCfg['exclude_workspace_ids'] ?? []), true);
    }

    private function importWorkspace(array $ws, array $instances, string $runStart, array &$stats): void
    {
        $now = now_iso();
        $wsId = $ws['id'];
        $this->workspaces->upsertFromScan($ws, $now);
        $this->users->replace('workspace', $wsId, $ws['users'] ?? []);
        $stats['workspaces']++;

        foreach ($ws['datasets'] ?? [] as $d) {
            $this->datasets->upsertFromScan($d, $wsId, $now);
            if (array_key_exists('tables', $d)) { // presente solo con datasetSchema abilitato
                $this->datasets->replaceSchema($d['id'], $d['tables']);
                $stats['tables'] += count($d['tables']);
                $stats['measures'] += array_sum(array_map(static fn ($t) => count($t['measures'] ?? []), $d['tables']));
            }
            $used = array_filter(array_map(static fn ($u) => $instances[$u['datasourceInstanceId']] ?? null, $d['datasourceUsages'] ?? []));
            $this->datasets->replaceDatasources($d['id'], $used);
            $this->users->replace('dataset', $d['id'], $d['users'] ?? []);
            $stats['datasets']++;
        }

        // Prima i report "originali", poi le copie pubblicate nelle app (collegate all'originale)
        $appCopies = [];
        foreach ($ws['reports'] ?? [] as $r) {
            if (!empty($r['appId']) && !empty($r['originalReportObjectId'])) {
                $appCopies[] = $r;
                continue;
            }
            $url = $this->urls->report($wsId, $r['id'], null, $r['reportType'] ?? null);
            $this->reports->upsertFromScan($r, $wsId, $url, $now);
            $this->users->replace('report', $r['id'], $r['users'] ?? []);
            $stats['reports']++;
        }
        foreach ($appCopies as $r) {
            $appUrl = $this->urls->report($wsId, $r['id'], $r['appId'], $r['reportType'] ?? null);
            if ($this->reports->exists($r['originalReportObjectId'])) {
                $this->reports->setAppLink($r['originalReportObjectId'], $r['appId'], $appUrl);
                // chi vede l'app vede il report: i permessi dell'app si sommano a quelli dell'originale
                $this->users->replace('report', $r['originalReportObjectId'], $r['users'] ?? [], append: true);
            } else {
                $this->reports->upsertFromScan($r, $wsId, $appUrl, $now);
                $this->users->replace('report', $r['id'], $r['users'] ?? []);
                $stats['reports']++;
            }
        }

        foreach ($ws['dashboards'] ?? [] as $d) {
            if (!empty($d['appId']) && !empty($d['originalDashboardObjectId']) && $this->dashboards->exists($d['originalDashboardObjectId'])) {
                $this->dashboards->setAppLink($d['originalDashboardObjectId'], $d['appId'], $this->urls->dashboard($wsId, $d['id'], $d['appId']));
                continue;
            }
            $this->dashboards->upsertFromScan($d, $wsId, $this->urls->dashboard($wsId, $d['id'], null), $now);
            $this->users->replace('dashboard', $d['id'], $d['users'] ?? []);
            $stats['dashboards']++;
        }

        // Ciò che non è più presente nel workspace scansionato viene marcato come eliminato
        $stats['deleted'] += $this->reports->markMissingInWorkspace($wsId, $runStart)
            + $this->datasets->markMissingInWorkspace($wsId, $runStart)
            + $this->dashboards->markMissingInWorkspace($wsId, $runStart);
    }
}
