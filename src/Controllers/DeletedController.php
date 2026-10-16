<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\DeletedItemRepository;

/** Amministrazione → Eliminati da Power BI: spostare i testi sul sostituto, cancellare del tutto. */
final class DeletedController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/eliminati */
    public function index(): void
    {
        $repo = $this->app->repo(DeletedItemRepository::class);
        $rows = $repo->all();
        foreach ($rows as &$r) {
            $r['suggest'] = $r['manual'] > 0 && !$r['replaced_by'] ? $repo->suggest($r['entity_type'], $r['id']) : null;
        }
        unset($r);
        $this->render('admin/deleted', [
            'title'   => 'Eliminati da Power BI',
            'rows'    => $rows,
            'choices' => ['report' => $repo->activeChoices('report'), 'dashboard' => $repo->activeChoices('dashboard')],
            'days'    => (int) $this->app->config()->get('sync.purge_deleted_after_days', 90),
        ]);
    }

    /** POST /admin/eliminati/sposta — sposta i testi delle righe spuntate sul report scelto per ciascuna */
    public function move(): void
    {
        $repo = $this->app->repo(DeletedItemRepository::class);
        $targets = (array) $this->request->input('target', []);
        $pairs = 0;
        foreach ((array) $this->request->input('pick', []) as $key) {
            [$type, $from] = array_pad(explode('|', (string) $key, 2), 2, '');
            $to = (string) ($targets[$key] ?? '');
            if ($to === '' || !in_array($type, ['report', 'dashboard'], true)) {
                continue;
            }
            $repo->moveManual($type, $from, $to);
            $type === 'report' ? $this->app->indexer()->indexReport($to) : $this->app->indexer()->indexDashboard($to);
            $pairs++;
        }
        Session::flash($pairs ? 'success' : 'warning', $pairs
            ? "Testi spostati per $pairs " . ($pairs === 1 ? 'elemento' : 'elementi') . '. Lo storico delle versioni è stato spostato con loro.'
            : 'Nessuna riga spuntata con un sostituto scelto.');
        $this->redirect(url('admin/eliminati'));
    }

    /** POST /admin/eliminati/elimina — cancella del tutto un elemento già sparito da Power BI */
    public function delete(): void
    {
        $type = (string) $this->request->input('type');
        $ok = $this->app->repo(DeletedItemRepository::class)->deleteOne($type, (string) $this->request->input('id'));
        Session::flash($ok ? 'success' : 'danger', $ok ? 'Eliminato del tutto da Ramingo.' : 'Elemento non trovato, oppure ancora presente in Power BI.');
        $this->redirect(url('admin/eliminati'));
    }
}
