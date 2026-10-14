<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\ArtifactUserRepository;
use Ramingo\Models\DashboardRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Catalog\CardPresenter;

final class DashboardController extends BaseController
{
    /** GET /dashboard/{id} */
    public function show(string $id): void
    {
        $dashboards = $this->app->repo(DashboardRepository::class);
        $d = $dashboards->find($id) ?? $this->abort(404, 'Dashboard non trovata nel catalogo.');

        $notes  = $this->app->repo(AnnotationRepository::class)->currentFor('dashboard', [$id])[$id] ?? [];
        $owners = $this->app->repo(ArtifactUserRepository::class)->owners('dashboard', $id);

        $this->render('dashboard/show', [
            'title'       => $d['name'],
            'd'           => $d,
            'access'      => $this->app->access()->states($this->user, [['entity_type' => 'dashboard'] + $d])['dashboard:' . $id],
            'notes'       => $notes,
            'description' => CardPresenter::bestDescription(null, $notes),
            'tags'        => $this->app->repo(TagRepository::class)->forEntities('dashboard', [$id])[$id] ?? [],
            'tagsInUse'   => $this->app->repo(TagRepository::class)->inUse(),
            'owners'      => $owners,
            'tiles'       => $dashboards->tiles($id),
            'canEdit'     => $this->canEdit($owners),
        ]);
    }
}
