<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Models\AreaRepository;
use Ramingo\Models\CatalogRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Search\Text;

final class SearchController extends BaseController
{
    /**
     * GET /api/search?q=...  — suggerimenti istantanei per la barra di ricerca.
     * Prima le scorciatoie verso un filtro (categorie e tag il cui nome corrisponde), poi i report.
     * In tutto al massimo 8 voci, come consigliano le ricerche sull'autocompletamento.
     */
    public function api(): void
    {
        $q      = (string) $this->request->query('q', '');
        $scopes = $this->scopes($q);
        $data   = $this->searchCards($q, 8 - count($scopes));
        $this->json([
            'concepts' => array_map(static fn ($c) => ['label' => $c['label'], 'input' => $c['input'], 'expanded' => $c['expanded']], $data['concepts']),
            'mode'     => $data['mode'],
            'scopes'   => $scopes,
            'results'  => array_map(static fn ($c) => [
                'name'      => $c['name'],
                'kind'      => CatalogRepository::KINDS[$c['kind']] ?? $c['kind'],
                'workspace' => $c['workspace_name'],
                'url'       => $c['detail_url'],
                'why'       => $c['why'],
            ], $data['cards']),
        ]);
    }

    /** Categorie e tag il cui nome contiene una parola della ricerca (al massimo 3). */
    private function scopes(string $q): array
    {
        $tokens = array_filter(Text::tokens($q), static fn ($t) => strlen($t) >= 3);
        if (!$tokens) {
            return [];
        }
        $matches = static function (string $name) use ($tokens): bool {
            foreach (Text::tokens($name) as $w) {
                foreach ($tokens as $t) {
                    if (str_starts_with($w, $t) || str_starts_with($t, $w) && strlen($w) >= 4) {
                        return true;
                    }
                }
            }
            return false;
        };
        $out = [];
        $areas = $this->app->repo(AreaRepository::class);
        foreach ($this->areaFacets($areas, $this->app->repo(CatalogRepository::class)->facets([])['areas']) as $a) {
            if ($a['value'] !== 'none' && $a['n'] > 0 && $matches($a['label'])) {
                $out[] = ['type' => 'Categoria', 'label' => $a['label'], 'n' => $a['n'], 'url' => url('', ['area' => [$a['value']]])];
            }
        }
        foreach ($this->app->repo(TagRepository::class)->inUse() as $t) {
            if ($matches($t['tag'])) {
                $out[] = ['type' => 'Tag', 'label' => '#' . $t['tag'], 'n' => (int) $t['n'], 'url' => url('', ['tag' => [$t['tag']]])];
            }
        }
        usort($out, static fn ($a, $b) => $b['n'] <=> $a['n']);
        return array_slice($out, 0, 3);
    }
}
