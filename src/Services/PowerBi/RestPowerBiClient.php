<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

use Ramingo\Services\Http\HttpClient;
use Ramingo\Services\Http\HttpException;

/**
 * Implementazione reale basata su:
 *  - Admin API "WorkspaceInfo" (Scanner API): elenco completo e metadati di TUTTO il tenant
 *  - API Reports "Get Pages In Group": pagine di un report (serve accesso al workspace)
 *
 * Documentazione: https://learn.microsoft.com/rest/api/power-bi/admin/workspace-info-post-workspace-info
 */
final class RestPowerBiClient implements PowerBiClient
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly TokenProvider $tokens,
        private readonly array $cfg,
    ) {
    }

    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function modifiedWorkspaceIds(?string $since, bool $excludePersonal): array
    {
        $query = [
            'excludePersonalWorkspaces' => $excludePersonal ? 'true' : 'false',
            'excludeInActiveWorkspaces' => 'true',
        ];
        if ($since !== null) {
            $query['modifiedSince'] = $since; // la Scanner API accetta al massimo 30 giorni indietro
        }
        $data = $this->get('admin/workspaces/modified?' . http_build_query($query));
        return array_column($data, 'id');
    }

    public function startScan(array $workspaceIds): string
    {
        $query = http_build_query([
            'lineage'            => 'True',
            'datasourceDetails'  => 'True',
            'datasetSchema'      => 'True',  // tabelle, colonne, misure (richiede impostazione tenant)
            'datasetExpressions' => 'True',  // DAX e Power Query (richiede impostazione tenant)
            'getArtifactUsers'   => 'True',  // chi ha accesso a cosa
        ]);
        $res = $this->call('POST', 'admin/workspaces/getInfo?' . $query, ['workspaces' => array_values($workspaceIds)]);
        return (string) ($res['id'] ?? throw new HttpException('Scanner API: scanId assente nella risposta.'));
    }

    public function scanStatus(string $scanId): string
    {
        return (string) ($this->get('admin/workspaces/scanStatus/' . rawurlencode($scanId))['status'] ?? 'Failed');
    }

    public function scanResult(string $scanId): array
    {
        return $this->get('admin/workspaces/scanResult/' . rawurlencode($scanId));
    }

    public function reportPages(string $workspaceId, string $reportId): ?array
    {
        try {
            $data = $this->get(sprintf('groups/%s/reports/%s/pages', rawurlencode($workspaceId), rawurlencode($reportId)));
        } catch (HttpException $e) {
            if (in_array($e->status, [401, 403, 404], true)) {
                $this->lastError = self::reason($e);
                return null; // il service principal non è membro del workspace
            }
            throw $e;
        }
        return array_map(static fn (array $p) => [
            'name'        => (string) $p['name'],
            'displayName' => (string) ($p['displayName'] ?? $p['name']),
            'order'       => (int) ($p['order'] ?? 0),
        ], $data);
    }

    public function datasetRefreshes(string $workspaceId, string $datasetId): ?array
    {
        try {
            $data = $this->get(sprintf('groups/%s/datasets/%s/refreshes?%%24top=10', rawurlencode($workspaceId), rawurlencode($datasetId)));
        } catch (HttpException $e) {
            if (in_array($e->status, [401, 403, 404], true)) {
                $this->lastError = self::reason($e);
                return null; // il service principal non è membro del workspace del modello
            }
            throw $e;
        }
        return array_map(static fn (array $r) => [
            'status'    => (string) ($r['status'] ?? 'Unknown'),
            'startTime' => $r['startTime'] ?? null,
            'endTime'   => $r['endTime'] ?? null,
        ], $data);
    }

    /**
     * Scarica il report in modalità "connessione dinamica" (LiveConnect: senza dati, solo struttura),
     * lo legge e cancella subito il file temporaneo.
     */
    public function reportDefinition(string $workspaceId, string $reportId): ?array
    {
        $url = rtrim($this->cfg['api_base'], '/') . sprintf('/groups/%s/reports/%s/Export?downloadType=LiveConnect', rawurlencode($workspaceId), rawurlencode($reportId));
        $res = $this->http->request('GET', $url, ['Authorization' => 'Bearer ' . $this->tokens->token()]);
        if ($res['status'] !== 200 || !str_starts_with($res['body'], "PK")) { // un .pbix è un archivio zip
            // nessun accesso al workspace, download non consentito, o report non esportabile
            $json = json_decode((string) $res['body'], true);
            $code = is_array($json) ? ($json['error']['code'] ?? $json['code'] ?? null) : null;
            $this->lastError = 'HTTP ' . $res['status'] . (is_string($code) ? ' – ' . $code : ($res['status'] === 200 ? ' – il file ricevuto non è un .pbix' : ''));
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ramingo_pbix_');
        try {
            file_put_contents($tmp, $res['body']);
            return (new \Ramingo\Services\Definition\ReportDefinitionParser())->parsePbix($tmp);
        } catch (\RuntimeException $e) {
            $this->lastError = 'struttura non leggibile: ' . $e->getMessage();
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /** GET che restituisce direttamente "value" se presente (formato OData). */
    private function get(string $path): array
    {
        $json = $this->call('GET', $path);
        return isset($json['value']) && is_array($json['value']) ? $json['value'] : $json;
    }

    private function call(string $method, string $path, ?array $payload = null): array
    {
        $url     = rtrim($this->cfg['api_base'], '/') . '/' . $path;
        $headers = ['Authorization' => 'Bearer ' . $this->tokens->token()];
        $res     = $payload === null
            ? $this->http->getJson($url, $headers)
            : $this->http->postJson($url, $payload, $headers);

        if ($res['status'] < 200 || $res['status'] >= 300) {
            $err    = $res['json']['error'] ?? [];
            $detail = trim(implode(' - ', array_filter([
                is_string($err['code'] ?? null) ? $err['code'] : null,
                is_string($err['message'] ?? null) ? $err['message'] : null,
            ])));
            if ($detail === '' || $detail === 'Bad Request') {
                $detail = trim(substr((string) $res['body'], 0, 500)) ?: 'nessun dettaglio da Power BI';
            }
            throw new HttpException(sprintf('Power BI %s %s -> HTTP %d: %s', $method, strtok($path, '?'), $res['status'], $detail), $res['status']);
        }
        return is_array($res['json']) ? $res['json'] : [];
    }

    /** «HTTP 401 – PowerBINotAuthorizedException»: codice di Power BI, senza dettagli lunghi. */
    private static function reason(HttpException $e): string
    {
        $msg = $e->getMessage();
        $code = preg_match('~HTTP \d+: ([A-Za-z]+)~', $msg, $m) ? $m[1] : null;
        return 'HTTP ' . $e->status . ($code ? ' – ' . $code : '');
    }
}
