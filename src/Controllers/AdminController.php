<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\AiRequestRepository;
use Ramingo\Models\SearchLogRepository;
use Ramingo\Models\SearchRepository;
use Ramingo\Models\SyncRunRepository;

/** Amministrazione: pagina indice con stato e collegamenti alle sezioni. */
final class AdminController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin */
    public function index(): void
    {
        $ai = $this->app->aiConnections();
        $this->render('admin/index', [
            'title'          => 'Amministrazione',
            'runs'           => $this->app->repo(SyncRunRepository::class)->recent(10),
            'fts'            => $this->app->repo(SearchRepository::class)->fts(),
            'pbiMode'        => $this->app->powerBiSettings()->mode(),
            'aiEnabled'      => $ai->enabled(),
            'aiCompany'      => $ai->company()?->label(),
            'aiCompanyError' => $ai->companyError(),
            'aiRequests'     => $this->app->repo(AiRequestRepository::class)->recent(15),
        ]);
    }

    /**
     * GET /admin/ricerche — cosa cercano i colleghi e cosa non trovano.
     * Per le ricerche senza risultati si rifà la ricerca adesso: dopo aver aggiunto un tag o una descrizione
     * si vede subito se il problema è risolto.
     */
    public function searches(): void
    {
        $days = in_array((int) $this->request->query('giorni', 90), [7, 30, 90, 365], true) ? (int) $this->request->query('giorni', 90) : 90;
        $log  = $this->app->repo(SearchLogRepository::class);
        $empty = $log->grouped($days, true, 100);
        foreach ($empty as &$r) {
            $r['now'] = count($this->app->search()->search($r['example'], 500)['hits']);
        }
        unset($r);
        $this->render('admin/searches', [
            'title' => 'Ricerche',
            'days'  => $days,
            'stats' => $log->stats($days),
            'empty' => $empty,
            'top'   => $log->grouped($days, false, 25),
        ]);
    }
}
