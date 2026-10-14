<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\AreaRepository;
use Ramingo\Models\CatalogRepository;
use Ramingo\Models\SyncRunRepository;

final class CatalogController extends BaseController
{
    /** Ordinamenti disponibili (chiave URL => etichetta). */
    public const SORTS = ['area' => 'Per categoria', 'name' => 'Per nome', 'recent' => 'Dati più recenti'];

    /** GET /  — pagina iniziale: ricerca + catalogo filtrabile per categoria, workspace, tipo, approvazione, tag. */
    public function index(): void
    {
        $areas   = $this->app->repo(AreaRepository::class);
        $filters = [];
        foreach (['area', 'workspace', 'kind', 'endorsement', 'tag'] as $k) {
            $filters[$k] = trim((string) $this->request->query($k, ''));
        }
        $query = $filters;
        if (ctype_digit($filters['area'])) {
            $query['area_ids'] = $areas->subtreeIds((int) $filters['area']);
        }
        $sort = (string) $this->request->query('sort', 'area');
        if (!isset(self::SORTS[$sort])) {
            $sort = 'area';
        }
        $pageSize = (int) $this->app->config()->get('catalog.page_size', 48);
        $page     = max(1, (int) $this->request->query('page', 1));

        $catalog = $this->app->repo(CatalogRepository::class);
        $result  = $catalog->find($query, $pageSize, ($page - 1) * $pageSize, $sort);
        $cards   = $this->visibleCards($this->cards()->present($result['items'], $this->user));
        $facets  = $catalog->facets($query);
        $facets['areaTree'] = $this->areaFacets($areas, $facets['areas']);
        $groups  = $sort === 'area' ? $this->groupByArea($cards, $areas) : null;

        $this->render('catalog/index', [
            'title'      => 'Catalogo',
            'homeSearch' => true,   // la ricerca grande sostituisce quella dell'intestazione
            'cards'      => $cards,
            // un solo gruppo (es. nessuna categoria ancora assegnata) = elenco semplice, senza titoli
            'groups'     => $groups !== null && count($groups) > 1 ? $groups : null,
            'total'      => $result['total'],
            'page'       => $page,
            'pages'      => (int) ceil($result['total'] / $pageSize),
            'filters'    => $filters,
            'sort'       => $sort,
            'sorts'      => self::SORTS,
            'facets'     => $facets,
            'labels'     => $this->activeLabels($filters, $facets, $areas),
            'counts'     => $catalog->counts(),
            'examples'   => (array) $this->app->config()->get('catalog.examples', []),
            'lastSync'   => $this->app->repo(SyncRunRepository::class)->lastSuccessful(),
            'demo'       => $this->app->powerBiSettings()->mode() === 'fixture',
        ]);
    }

    /** Etichette leggibili dei filtri attivi, per i «chip» rimovibili. */
    private function activeLabels(array $filters, array $facets, AreaRepository $areas): array
    {
        $find = static function (array $rows, string $value): ?string {
            foreach ($rows as $r) {
                if ((string) $r['value'] === $value) {
                    return (string) $r['label'];
                }
            }
            return null;
        };
        $out = [];
        if ($filters['area'] !== '') {
            $out['area'] = $filters['area'] === 'none' ? 'Da classificare' : ($areas->paths()[(int) $filters['area']] ?? 'Categoria');
        }
        if ($filters['workspace'] !== '') {
            $out['workspace'] = 'Workspace ' . ($find($facets['workspaces'], $filters['workspace']) ?? '');
        }
        if ($filters['kind'] !== '') {
            $out['kind'] = CatalogRepository::KINDS[$filters['kind']] ?? $filters['kind'];
        }
        if ($filters['endorsement'] !== '') {
            $out['endorsement'] = $filters['endorsement'] === 'Certified' ? 'Certificati' : 'Promossi';
        }
        if ($filters['tag'] !== '') {
            $out['tag'] = '#' . $filters['tag'];
        }
        return $out;
    }

    /** GET /fonti — spiega le quattro provenienze dei dati. */
    public function sources(): void
    {
        $this->render('catalog/sources', ['title' => 'Da dove arrivano le informazioni']);
    }

    /** Gruppi nell'ordine dell'albero delle categorie, con il percorso completo come titolo. */
    private function groupByArea(array $cards, AreaRepository $areas): array
    {
        $paths = $areas->paths();
        $order = array_flip(array_map(static fn ($a) => (int) $a['id'], $areas->flat()));
        $groups = [];
        foreach ($cards as $c) {
            $k = $c['area_id'] !== null ? (int) $c['area_id'] : 'none';
            $groups[$k] ??= [
                'name'  => $k === 'none' ? 'Da classificare' : ($paths[$k] ?? $c['area_name']),
                'icon'  => $c['area_icon'] ?? 'fa-solid fa-inbox',
                'cards' => [],
            ];
            $groups[$k]['cards'][] = $c;
        }
        uksort($groups, static fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        return $groups;
    }

    /** Filtro ad albero: per ogni categoria il totale include le sottocategorie. */
    private function areaFacets(AreaRepository $areas, array $direct): array
    {
        $counts = [];
        $none = 0;
        foreach ($direct as $f) {
            $f['value'] === null ? $none = (int) $f['n'] : $counts[(int) $f['value']] = (int) $f['n'];
        }
        $sum = function (array $node) use (&$sum, $counts): int {
            $n = $counts[(int) $node['id']] ?? 0;
            foreach ($node['children'] as $c) {
                $n += $sum($c);
            }
            return $n;
        };
        $out = [];
        $walk = function (array $nodes) use (&$walk, &$out, $sum): void {
            foreach ($nodes as $node) {
                $total = $sum($node);
                if ($total > 0) {
                    $out[] = ['value' => (int) $node['id'], 'label' => $node['name'], 'icon' => $node['icon'], 'depth' => $node['depth'], 'n' => $total];
                    $walk($node['children']);
                }
            }
        };
        $walk($areas->tree());
        if ($none) {
            $out[] = ['value' => 'none', 'label' => 'Da classificare', 'icon' => 'fa-solid fa-inbox', 'depth' => 0, 'n' => $none];
        }
        return $out;
    }
}
