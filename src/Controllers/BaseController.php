<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Controller;
use Ramingo\Core\Session;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\AreaRepository;
use Ramingo\Models\CatalogRepository;
use Ramingo\Models\SearchLogRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Auth\AccessService;
use Ramingo\Services\Catalog\CardPresenter;
use Ramingo\Services\Catalog\CatalogFilters;

/** Helper condivisi dai controller dell'applicazione. */
abstract class BaseController extends Controller
{
    protected function cards(): CardPresenter
    {
        return new CardPresenter(
            $this->app->repo(AnnotationRepository::class),
            $this->app->repo(TagRepository::class),
            $this->app->access(),
            (int) $this->app->config()->get('catalog.stale_days', 7),
        );
    }

    /**
     * Ricerca + schede pronte per le viste (usata dalla ricerca e dall'AI).
     * @return array{q:string, concepts:array, mode:string, cards:array}
     */
    protected function searchCards(string $q, int $limit): array
    {
        if (mb_strlen($q) < 2) {
            return ['q' => $q, 'concepts' => [], 'mode' => 'all', 'cards' => []];
        }
        $result = $this->app->search()->search($q, $limit * 2);
        $rows   = $this->app->repo(\Ramingo\Models\CatalogRepository::class)->byKeys(array_keys($result['hits']));
        $cards  = $this->visibleCards($this->cards()->present($rows, $this->user));
        foreach ($cards as &$c) {
            $c['why'] = $result['hits'][$c['key']]['why'] ?? [];
        }
        return ['q' => $q, 'concepts' => $result['concepts'], 'mode' => $result['mode'], 'cards' => array_slice($cards, 0, $limit)];
    }

    /** Identità per log e limiti AI: oid Entra, oppure IP se non c'è login. */
    protected function aiUserId(): string
    {
        return $this->user->oid ?? 'locale:' . $this->request->ip;
    }

    /** Applica l'opzione catalog.hide_inaccessible. */
    protected function visibleCards(array $cards): array
    {
        if (!$this->app->config()->get('catalog.hide_inaccessible', false) || $this->user->isAdmin()) {
            return $cards;
        }
        return array_values(array_filter($cards, static fn ($c) => $c['access'] !== AccessService::NO));
    }

    /** Può scrivere descrizioni? Amministratori di Ramingo e proprietari del contenuto in Power BI. */
    protected function canEdit(array $owners): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }
        $email = strtolower((string) $this->user->email);
        return $email !== '' && in_array($email, array_map('strtolower', array_filter(array_column($owners, 'email'))), true);
    }

    /** Può vedere DAX e Power Query? Regola configurabile (catalog.show_expressions). */
    protected function canSeeExpressions(string $accessState): bool
    {
        return match ($this->app->config()->get('catalog.show_expressions', 'accessible')) {
            'all'        => true,
            'admins'     => $this->user->isAdmin(),
            default      => $this->user->isAdmin() || $accessState === AccessService::YES
                || $this->app->config()->get('auth.mode') !== 'entra',
        };
    }

    /** Dati della pagina: elenco filtrato + testata + collegamento AI. Usato anche da AiController. */
    protected function catalogPage(string $q, string $here, array $extra = []): array
    {
        return $this->browse($q, $here) + $extra + [
            'title'      => $q !== '' ? "Ricerca: $q" : 'Catalogo',
            'homeSearch' => true,   // la ricerca grande sostituisce quella dell'intestazione
            'counts'     => $this->app->repo(CatalogRepository::class)->counts(),
            'examples'   => (array) $this->app->config()->get('catalog.examples', []),
            'lastSync'   => $this->app->repo(\Ramingo\Models\SyncRunRepository::class)->lastSuccessful(),
            'demo'       => $this->app->powerBiSettings()->mode() === 'fixture',
            'ai'         => $q !== '' ? $this->app->aiAssistant()->describeConnection($this->user) : null,
            'scripts'    => [],
        ];
    }

    /** Ordinamenti del catalogo (chiave URL => etichetta). Con una ricerca si aggiunge «Più pertinenti». */
    public const SORTS = ['area' => 'Per categoria', 'name' => 'Per nome', 'recent' => 'Dati più recenti'];

    /**
     * Catalogo e ricerca sono la stessa pagina: elenco + colonna filtri. Questo metodo prepara tutto
     * quello che serve alla vista catalog/index, con o senza una ricerca ($q).
     */
    protected function browse(string $q, string $here): array
    {
        $areas   = $this->app->repo(AreaRepository::class);
        $catalog = $this->app->repo(CatalogRepository::class);
        $cfg     = $this->app->config();
        $filters = CatalogFilters::fromRequest($this->request);
        $query   = $filters->toQuery($areas);

        $search = null;
        if (mb_strlen($q) >= 2) {
            $search = $this->app->search()->search($q, 500);
            $query['keys'] = array_keys($search['hits']);
        }

        $sorts = $search ? ['relevance' => 'Più pertinenti'] + self::SORTS : self::SORTS;
        $defaultSort = $search ? 'relevance' : 'area';
        $sort = (string) $this->request->query('sort', $defaultSort);
        if (!isset($sorts[$sort])) {
            $sort = $defaultSort;
        }
        $view = $this->viewMode();

        $rows     = $catalog->all($query, $sort);
        $total    = count($rows);
        $pageSize = max(10, (int) $cfg->get('catalog.page_size', 60));
        $pages    = max(1, (int) ceil($total / $pageSize));
        $page     = min($pages, max(1, (int) $this->request->query('page', 1)));
        $cards    = $this->visibleCards($this->cards()->present(array_slice($rows, ($page - 1) * $pageSize, $pageSize), $this->user));
        if ($search) {
            foreach ($cards as &$c) {
                $c['why']  = $search['hits'][$c['key']]['why'] ?? [];
                $c['weak'] = $search['hits'][$c['key']]['weak'] ?? false;
            }
            unset($c);
            $this->logSearch($q, $total, $filters, $page);
        }

        // Parametri che ogni link della pagina deve conservare
        $keep = array_filter(['q' => $search ? $q : '', 'sort' => $sort !== $defaultSort ? $sort : '', 'view' => $view !== 'grid' ? $view : '']);
        $link = static fn (CatalogFilters $f, array $extra = []) => url($here, $f->params() + $keep + $extra);

        $facets = $catalog->facets($query);
        $facets['areaTree'] = $this->areaFacets($areas, $facets['areas']);

        return [
            'q'          => $q,
            'search'     => $search,
            'concepts'   => $search['concepts'] ?? [],
            'mode'       => $search['mode'] ?? 'all',
            'corrected'  => $search['corrected'] ?? [],
            'here'       => $here,
            'filters'    => $filters,
            'keep'       => $keep,
            'link'       => $link,
            'sort'       => $sort,
            'sorts'      => $sorts,
            'viewMode'   => $view,
            'tagsExist'  => (bool) $this->app->repo(TagRepository::class)->inUse(), // la sezione Tag resta anche se i report filtrati non ne hanno
            'sideHidden' => ($_COOKIE['rg_side'] ?? '') === 'hidden', // colonna filtri nascosta dall'utente (computer)
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'pageSize'   => $pageSize,
            'groups'     => $this->groups($cards, $sort === 'area' && !$search, $sort !== 'recent' && $sort !== 'relevance', (bool) $search, $areas),
            'facets'     => $facets,
            'active'     => $this->activeFilters($filters, $facets, $areas, $link),
            'cardsCount' => count($cards),
        ];
    }

    /** Schede o elenco: scelta dell'utente, ricordata in un cookie. */
    private function viewMode(): string
    {
        $v = (string) $this->request->query('view', '');
        if (in_array($v, ['grid', 'list'], true)) {
            setcookie('rg_view', $v, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
            return $v;
        }
        return ($_COOKIE['rg_view'] ?? '') === 'list' ? 'list' : 'grid';
    }

    /**
     * Gruppi da mostrare. Per categoria: un gruppo per categoria, nell'ordine dell'albero.
     * Se $splitOld, in ogni gruppo i report con dati fermi da più di catalog.old_after_days vanno in fondo.
     * Se $splitWeak (ricerca), i report trovati solo tra i nomi tecnici dei dati vanno in fondo, separati.
     * @return array<int, array{name:?string, icon:?string, cards:array, old:array, weak:array}>
     */
    private function groups(array $cards, bool $byArea, bool $splitOld, bool $splitWeak, AreaRepository $areas): array
    {
        $raw = [['name' => null, 'icon' => null, 'cards' => $cards]];
        if ($byArea) {
            $paths = $areas->paths();
            $order = array_flip(array_map(static fn ($a) => (int) $a['id'], $areas->flat()));
            $byKey = [];
            foreach ($cards as $c) {
                $k = $c['area_id'] !== null ? (int) $c['area_id'] : 'none';
                $byKey[$k] ??= [
                    'name'  => $k === 'none' ? 'Da classificare' : ($paths[$k] ?? $c['area_name']),
                    'icon'  => $k === 'none' ? 'fa-solid fa-inbox' : ($c['area_icon'] ?: 'fa-solid fa-shapes'),
                    'cards' => [],
                ];
                $byKey[$k]['cards'][] = $c;
            }
            uksort($byKey, static fn ($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            $raw = count($byKey) > 1 ? array_values($byKey) : [['name' => null, 'icon' => null, 'cards' => $cards]];
        }
        $limit = gmdate('Y-m-d\TH:i:s\Z', time() - 86400 * (int) $this->app->config()->get('catalog.old_after_days', 90));
        return array_map(static function (array $g) use ($splitOld, $splitWeak, $limit): array {
            $g['old'] = [];
            $g['weak'] = [];
            if ($splitWeak) {
                $g['weak']  = array_values(array_filter($g['cards'], static fn ($c) => !empty($c['weak'])));
                $g['cards'] = array_values(array_filter($g['cards'], static fn ($c) => empty($c['weak'])));
            }
            if ($splitOld) {
                $isOld = static fn ($c) => !empty($c['data_refreshed_at']) && $c['data_refreshed_at'] < $limit;
                $g['old']   = array_values(array_filter($g['cards'], $isOld));
                $g['cards'] = array_values(array_filter($g['cards'], static fn ($c) => !$isOld($c)));
            }
            return $g;
        }, $raw);
    }

    /** Filtri attivi con il link che li toglie, per i «chip» sopra i risultati. */
    private function activeFilters(CatalogFilters $filters, array $facets, AreaRepository $areas, callable $link): array
    {
        $paths  = $areas->paths();
        $wsName = array_column($facets['workspaces'], 'label', 'value');
        $out = [];
        foreach ($filters->get('area') as $a) {
            $out[] = ['label' => $a === 'none' ? 'Da classificare' : ($paths[(int) $a] ?? 'Categoria'), 'url' => $link($filters->without('area', $a))];
        }
        foreach ($filters->get('tag') as $t) {
            $out[] = ['label' => '#' . $t, 'url' => $link($filters->without('tag', $t))];
        }
        if ($filters->fresh !== '') {
            $out[] = ['label' => 'Dati: ' . mb_strtolower(CatalogFilters::FRESH[$filters->fresh]), 'url' => $link($filters->without('fresh'))];
        }
        foreach ($filters->get('ws') as $w) {
            $out[] = ['label' => 'Workspace ' . ($wsName[$w] ?? ''), 'url' => $link($filters->without('ws', $w))];
        }
        foreach ($filters->get('kind') as $k) {
            $out[] = ['label' => CatalogRepository::KINDS[$k] ?? $k, 'url' => $link($filters->without('kind', $k))];
        }
        foreach ($filters->get('appr') as $e) {
            $out[] = ['label' => $e === 'Certified' ? 'Certificati' : 'Promossi', 'url' => $link($filters->without('appr', $e))];
        }
        return $out;
    }

    /** Albero delle categorie per i filtri: ogni categoria conta anche le sottocategorie. */
    protected function areaFacets(AreaRepository $areas, array $direct): array
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
                $out[] = ['value' => (string) $node['id'], 'label' => $node['name'], 'icon' => $node['icon'], 'depth' => (int) $node['depth'], 'n' => $sum($node)];
                $walk($node['children']);
            }
        };
        $walk($areas->tree());
        if ($none) {
            $out[] = ['value' => 'none', 'label' => 'Da classificare', 'icon' => 'fa-solid fa-inbox', 'depth' => 0, 'n' => $none];
        }
        return $out;
    }

    /**
     * Registra la ricerca (senza sapere chi l'ha fatta) per l'elenco «ricerche senza risultati».
     * Solo la ricerca «pulita» (nessun filtro, prima pagina) e una volta per sessione e testo.
     */
    private function logSearch(string $q, int $total, CatalogFilters $filters, int $page): void
    {
        if ($page !== 1 || !$filters->isEmpty() || $this->request->query('sort') !== null) {
            return;
        }
        $seen = (array) Session::get('rg_logged_q', []);
        $key  = mb_strtolower($q);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        Session::set('rg_logged_q', array_slice($seen, -50, null, true));
        $this->app->repo(SearchLogRepository::class)->log($q, $total);
    }
}
