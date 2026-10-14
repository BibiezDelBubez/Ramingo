<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\AiRequestRepository;
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
}
