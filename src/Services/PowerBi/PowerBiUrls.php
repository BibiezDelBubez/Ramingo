<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

/**
 * Costruzione dei link "Apri in Power BI" in un solo posto.
 * Il link porta l'utente nel servizio Power BI, che applica i SUOI permessi:
 * Ramingo non fa da proxy e non mostra mai dati dei report.
 */
final class PowerBiUrls
{
    public function __construct(private readonly string $webBase, private readonly string $tenantId)
    {
    }

    public function report(string $workspaceId, string $reportId, ?string $appId, ?string $type): string
    {
        $kind = $type === 'PaginatedReport' ? 'rdlreports' : 'reports';
        $path = $appId
            ? sprintf('groups/me/apps/%s/%s/%s', $appId, $kind, $reportId)
            : sprintf('groups/%s/%s/%s', $workspaceId, $kind, $reportId);
        return $this->build($path);
    }

    public function reportPage(string $reportUrl, string $pageName): string
    {
        // inserisce /<pageName> prima della query string
        [$base, $query] = array_pad(explode('?', $reportUrl, 2), 2, '');
        return $base . '/' . rawurlencode($pageName) . ($query !== '' ? '?' . $query : '');
    }

    public function dashboard(string $workspaceId, string $dashboardId, ?string $appId): string
    {
        $path = $appId
            ? sprintf('groups/me/apps/%s/dashboards/%s', $appId, $dashboardId)
            : sprintf('groups/%s/dashboards/%s', $workspaceId, $dashboardId);
        return $this->build($path);
    }

    public function workspace(string $workspaceId): string
    {
        return $this->build(sprintf('groups/%s/list', $workspaceId));
    }

    private function build(string $path): string
    {
        // ctid = tenant: evita problemi agli utenti ospiti / con più tenant
        return rtrim($this->webBase, '/') . '/' . $path . '?ctid=' . rawurlencode($this->tenantId);
    }
}
