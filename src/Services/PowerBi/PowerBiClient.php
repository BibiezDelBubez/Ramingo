<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

/**
 * Contratto verso Power BI. Due implementazioni:
 *  - RestPowerBiClient    -> tenant reale (API REST + Scanner API)
 *  - FixturePowerBiClient -> dati di esempio, per sviluppare senza credenziali
 * Il resto dell'app non sa quale delle due sta usando.
 */
interface PowerBiClient
{
    /**
     * Workspace modificati dopo $since (null = tutti).
     * @return string[] id dei workspace
     */
    public function modifiedWorkspaceIds(?string $since, bool $excludePersonal): array;

    /** Avvia una scansione (max 100 workspace). @return string scanId */
    public function startScan(array $workspaceIds): string;

    /** @return string NotStarted | Running | Succeeded | Failed */
    public function scanStatus(string $scanId): string;

    /** Risultato completo della scansione (workspaces + datasourceInstances). */
    public function scanResult(string $scanId): array;

    /**
     * Pagine di un report. Richiede che l'identità usata sia membro del workspace.
     * @return array<int, array{name:string, displayName:string, order:int}>|null  null = non accessibile
     */
    public function reportPages(string $workspaceId, string $reportId): ?array;

    /**
     * Definizione del report (pagine, visual e campi usati), senza dati.
     * Richiede che l'identità usata possa scaricare il report dal workspace.
     * @return array{pages: array, visuals: array}|null  null = non accessibile o non scaricabile
     */
    public function reportDefinition(string $workspaceId, string $reportId): ?array;

    /**
     * Ultimi aggiornamenti dei dati (refresh) di un modello semantico, dal più recente.
     * Richiede che l'identità usata sia membro del workspace del modello.
     * @return array<int, array{status:string, startTime:?string, endTime:?string}>|null  null = non accessibile
     */
    public function datasetRefreshes(string $workspaceId, string $datasetId): ?array;

    /** Motivo dell'ultimo «non accessibile» (es. «HTTP 401 – PowerBINotAuthorizedException»), per il riepilogo. */
    public function lastError(): ?string;
}
