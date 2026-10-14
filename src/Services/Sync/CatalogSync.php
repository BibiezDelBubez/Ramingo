<?php
declare(strict_types=1);

namespace Ramingo\Services\Sync;

use Ramingo\Models\DatasetRefreshRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\SyncRunRepository;
use Ramingo\Models\WorkspaceRepository;
use Ramingo\Services\Http\HttpException;
use Ramingo\Services\Insight\InsightService;
use Ramingo\Services\Logger;
use Ramingo\Services\PowerBi\PowerBiClient;
use Ramingo\Services\Search\SearchIndexer;

/**
 * Orchestrazione della sincronizzazione (eseguita SOLO da riga di comando: bin/sync.php).
 *
 *   1. elenco workspace (tutti, o solo i modificati dall'ultima sync)
 *   2. Scanner API a lotti di 100: avvio -> attesa -> risultato -> import
 *   3. pagine dei report (dove il service principal ha accesso)
 *   3b. struttura dei report e data dell'ultimo aggiornamento dei dati
 *   4. interpretazioni automatiche
 *   5. ricostruzione dell'indice di ricerca
 */
final class CatalogSync
{
    public function __construct(
        private readonly PowerBiClient $pbi,
        private readonly ScanResultImporter $importer,
        private readonly WorkspaceRepository $workspaces,
        private readonly ReportRepository $reports,
        private readonly SyncRunRepository $runs,
        private readonly InsightService $insights,
        private readonly SearchIndexer $indexer,
        private readonly DatasetRefreshRepository $refreshes,
        private readonly Logger $log,
        private readonly array $cfg,
    ) {
    }

    public function run(string $mode): array
    {
        $runStart = now_iso();
        $runId    = $this->runs->start($mode, $runStart);
        $stats    = [];

        try {
            $since = null;
            if ($mode === 'incremental') {
                $last  = $this->runs->lastSuccessful();
                $since = $last ? $this->clampSince($last['started_at']) : null;
            }
            $ids = $this->modifiedWorkspaceIds($since);
            $this->log->info(sprintf('Sync %s: %d workspace da scansionare', $mode, count($ids)), ['since' => $since]);
            $stats['workspaces_to_scan'] = count($ids);

            foreach (array_chunk($ids, (int) ($this->cfg['scan_batch_size'] ?? 100)) as $i => $batch) {
                $scanId = $this->pbi->startScan($batch);
                $this->waitFor($scanId);
                $result = $this->pbi->scanResult($scanId);
                $this->accumulate($stats, $this->importer->import($result, $runStart));
                $this->log->info(sprintf('Lotto %d importato (%d workspace)', $i + 1, count($batch)));
            }

            if ($mode === 'full') {
                $stats['deleted_workspaces'] = $this->workspaces->markDeletedNotSeenSince($runStart);
            }
            if ($this->cfg['fetch_report_pages'] ?? true) {
                $stats += $this->fetchPages();
            }
            if ($this->cfg['fetch_report_definitions'] ?? true) {
                $stats += $this->fetchDefinitions();
            }
            if ($this->cfg['fetch_data_refreshes'] ?? true) {
                $stats += $this->fetchRefreshes();
            }
            $stats['auto_insights_changed'] = $this->insights->refreshAll();
            $stats['indexed'] = $this->indexer->rebuildAll();

            $this->runs->finish($runId, 'ok', $stats);
            $this->log->info('Sync completata', $stats);
            return $stats;
        } catch (\Throwable $e) {
            $this->runs->finish($runId, 'failed', $stats, $e->getMessage());
            $this->log->error('Sync fallita: ' . $e->getMessage());
            throw $e;
        }
    }

    private function waitFor(string $scanId): void
    {
        $deadline = time() + (int) ($this->cfg['scan_timeout_seconds'] ?? 600);
        while (true) {
            $status = $this->pbi->scanStatus($scanId);
            if ($status === 'Succeeded') {
                return;
            }
            if ($status === 'Failed' || time() > $deadline) {
                throw new \RuntimeException("Scansione $scanId non riuscita (stato: $status).");
            }
            sleep((int) ($this->cfg['scan_poll_seconds'] ?? 5));
        }
    }

    private function fetchPages(): array
    {
        $ok = $denied = 0;
        foreach ($this->reports->needingPages() as $r) {
            $pages = $this->pbi->reportPages($r['workspace_id'], $r['id']);
            if ($pages === null) {
                $denied++;
            } else {
                $this->reports->replacePages($r['id'], $pages, 'pbi');
                $ok++;
            }
            $this->reports->touchPagesFetched($r['id'], now_iso());
        }
        return ['pages_fetched' => $ok, 'pages_not_accessible' => $denied];
    }

    /**
     * Quando sono stati aggiornati i dati di ogni modello usato dai report.
     * Ogni modello si ricontrolla al massimo ogni sync.refresh_check_minutes (60 di default).
     */
    private function fetchRefreshes(): array
    {
        $ok = $denied = 0;
        foreach ($this->refreshes->due((int) ($this->cfg['refresh_check_minutes'] ?? 60)) as $d) {
            $history = $this->pbi->datasetRefreshes($d['workspace_id'], $d['dataset_id']);
            $history === null ? $denied++ : $ok++;
            $this->refreshes->save($d['dataset_id'], $d['workspace_id'], $history, now_iso());
        }
        return ['data_refresh_checked' => $ok, 'data_refresh_not_accessible' => $denied];
    }

    /** Quali campi usa ogni report: si rilegge solo per i report nuovi o modificati. */
    private function fetchDefinitions(): array
    {
        $ok = $denied = 0;
        foreach ($this->reports->needingDefinition() as $r) {
            $def = $this->pbi->reportDefinition($r['workspace_id'], $r['id']);
            if ($def === null) {
                $denied++;
            } else {
                $this->reports->saveDefinition($r['id'], $def);
                $ok++;
            }
            $this->reports->touchDefinitionFetched($r['id'], now_iso());
        }
        return ['definitions_fetched' => $ok, 'definitions_not_accessible' => $denied];
    }

    /**
     * Elenco dei workspace da scansionare.
     * Se Power BI rifiuta la richiesta «solo i modificati» (HTTP 400), si ripiega
     * sull'elenco completo: costa qualche secondo in più ma la sincronizzazione va a buon fine.
     */
    private function modifiedWorkspaceIds(?string $since): array
    {
        $excludePersonal = (bool) ($this->cfg['exclude_personal_workspaces'] ?? true);
        try {
            return $this->pbi->modifiedWorkspaceIds($since, $excludePersonal);
        } catch (HttpException $e) {
            if ($since === null || $e->status !== 400) {
                throw $e;
            }
            $this->log->warning('Elenco dei workspace modificati rifiutato, uso l\'elenco completo', [
                'since' => $since, 'errore' => $e->getMessage(),
            ]);
            return $this->pbi->modifiedWorkspaceIds(null, $excludePersonal);
        }
    }

    /**
     * La Scanner API accetta modifiedSince solo tra 30 giorni e 30 minuti fa, in UTC.
     * Oltre i 30 giorni si rifà l'elenco completo; sotto i 30 minuti si arretra a 31 minuti fa
     * (rileggere qualche workspace in più non fa danni).
     */
    private function clampSince(string $iso): ?string
    {
        $utc   = new \DateTimeZone('UTC');
        $now   = new \DateTimeImmutable('now', $utc);
        $since = (new \DateTimeImmutable($iso))->setTimezone($utc);
        if ($since < $now->modify('-29 days')) {
            return null;
        }
        $latest = $now->modify('-31 minutes');
        if ($since > $latest) {
            $since = $latest;
        }
        return $since->format('Y-m-d\TH:i:s\Z');
    }

    private function accumulate(array &$stats, array $add): void
    {
        foreach ($add as $k => $v) {
            $stats[$k] = ($stats[$k] ?? 0) + $v;
        }
    }
}
