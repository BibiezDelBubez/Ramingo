<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

/**
 * Finto client che legge un JSON con la stessa forma delle risposte reali.
 * Permette di sviluppare e mostrare Ramingo prima di avere le credenziali.
 */
final class FixturePowerBiClient implements PowerBiClient
{
    private array $data;

    public function __construct(string $file)
    {
        $this->data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    public function modifiedWorkspaceIds(?string $since, bool $excludePersonal): array
    {
        return array_column($this->data['scanResult']['workspaces'], 'id');
    }

    public function startScan(array $workspaceIds): string
    {
        return 'fixture-scan';
    }

    public function scanStatus(string $scanId): string
    {
        return 'Succeeded';
    }

    public function scanResult(string $scanId): array
    {
        return $this->data['scanResult'];
    }

    public function reportPages(string $workspaceId, string $reportId): ?array
    {
        return $this->data['pages'][$reportId] ?? null;
    }

    public function reportDefinition(string $workspaceId, string $reportId): ?array
    {
        return $this->data['definitions'][$reportId] ?? null;
    }

    /** Dati di esempio: aggiornamento notturno, con qualche modello fermo da tempo per vedere l'avviso. */
    public function datasetRefreshes(string $workspaceId, string $datasetId): ?array
    {
        if (isset($this->data['refreshes'][$datasetId])) {
            return $this->data['refreshes'][$datasetId];
        }
        $daysAgo = [0, 0, 1, 0, 40][crc32($datasetId) % 5];
        $end = gmdate('Y-m-d\T01:12:00\Z', strtotime("-$daysAgo days"));
        return [['status' => 'Completed', 'startTime' => $end, 'endTime' => $end]];
    }
}
