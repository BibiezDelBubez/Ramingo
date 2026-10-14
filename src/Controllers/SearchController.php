<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\CatalogRepository;

final class SearchController extends BaseController
{
    /** GET /cerca?q=... */
    public function index(): void
    {
        $q = (string) $this->request->query('q', '');
        $this->render('search/index', $this->searchCards($q, 60) + [
            'title' => $q !== '' ? "Ricerca: $q" : 'Ricerca',
            'ai'    => $this->app->aiAssistant()->describeConnection($this->user),
        ]);
    }

    /** GET /api/search?q=...  — suggerimenti istantanei per la barra di ricerca. */
    public function api(): void
    {
        $data = $this->searchCards((string) $this->request->query('q', ''), 8);
        $this->json([
            'concepts' => array_map(static fn ($c) => ['label' => $c['label'], 'input' => $c['input'], 'expanded' => $c['expanded']], $data['concepts']),
            'mode'     => $data['mode'],
            'results'  => array_map(static fn ($c) => [
                'name'      => $c['name'],
                'kind'      => CatalogRepository::KINDS[$c['kind']] ?? $c['kind'],
                'workspace' => $c['workspace_name'],
                'url'       => $c['detail_url'],
                'why'       => $c['why'],
            ], $data['cards']),
        ]);
    }
}
