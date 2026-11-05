<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\CatalogRepository;

/**
 * Amministrazione → Nascosti: report, dashboard o interi workspace che non devono comparire
 * nel catalogo (contenuti tecnici, modelli di prova). Non è una protezione: chi ha il link
 * apre comunque la scheda, e i dati restano protetti dai permessi di Power BI.
 */
final class HiddenController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/nascosti */
    public function index(): void
    {
        $catalog = $this->app->repo(CatalogRepository::class);
        $byWs = [];
        foreach ($catalog->visibilityList() as $it) {
            $byWs[$it['workspace_id']] ??= ['id' => $it['workspace_id'], 'name' => $it['workspace_name'], 'hidden' => (bool) $it['ws_hidden'], 'items' => [], 'hiddenItems' => 0];
            $byWs[$it['workspace_id']]['items'][] = $it;
            $byWs[$it['workspace_id']]['hiddenItems'] += $it['hidden_reason'] !== null ? 1 : 0;
        }
        $this->render('admin/hidden', [
            'title'      => 'Nascosti dal catalogo',
            'workspaces' => array_values($byWs),
            'prefixes'   => $catalog->hiddenPrefixes(),
            'total'      => $catalog->hiddenCount(),
            'scripts'    => ['categories.js'], // filtro dell'elenco
        ]);
    }

    /** POST /admin/nascosti/imposta — kind (workspace|report|dashboard), id, hidden (1|0) */
    public function toggle(): void
    {
        $kind = (string) $this->request->input('kind');
        $hidden = $this->request->input('hidden') === '1';
        try {
            $this->app->repo(CatalogRepository::class)->setHidden($kind, (string) $this->request->input('id'), $hidden);
            Session::flash('success', ($kind === 'workspace' ? 'Workspace ' : 'Elemento ') . ($hidden ? 'nascosto dal catalogo.' : 'di nuovo visibile nel catalogo.'));
        } catch (\InvalidArgumentException $e) {
            Session::flash('danger', $e->getMessage());
        }
        $this->redirect(url('admin/nascosti') . '#ws-' . rawurlencode((string) $this->request->input('ws')));
    }

    /** POST /admin/nascosti/regole — prefissi del nome che nascondono automaticamente */
    public function rules(): void
    {
        $raw = (string) $this->request->input('prefixes', '');
        $catalog = $this->app->repo(CatalogRepository::class);
        $catalog->setHiddenPrefixes(preg_split('/[\r\n,]+/', $raw) ?: []);
        Session::flash('success', $catalog->hiddenPrefixes()
            ? 'Regola salvata: sono nascosti i nomi che iniziano con ' . implode(', ', array_map(static fn ($p) => '«' . $p . '»', $catalog->hiddenPrefixes())) . '.'
            : 'Regola tolta: nessun report è nascosto per il nome.');
        $this->redirect(url('admin/nascosti'));
    }
}
