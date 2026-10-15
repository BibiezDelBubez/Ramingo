<?php
declare(strict_types=1);

namespace Ramingo\Services\Catalog;

use Ramingo\Core\Request;
use Ramingo\Models\AreaRepository;

/**
 * Filtri scelti dall'utente nella colonna a sinistra, letti dall'indirizzo della pagina.
 * Unico punto che sa come si chiamano i parametri e cosa è valido (DRY tra catalogo, ricerca e AI).
 *
 *   ?area[]=3&area[]=none   categorie (con le sottocategorie), «none» = da classificare
 *   ?tag[]=benelux&tag[]=lavaggi
 *   ?ws[]=<id workspace>  ?kind[]=report  ?appr[]=Certified  ?fresh=week|month|old
 */
final class CatalogFilters
{
    public const MULTI = ['area', 'tag', 'ws', 'kind', 'appr'];
    public const FRESH = ['week' => 'Ultima settimana', 'month' => 'Ultimo mese', 'old' => 'Più vecchi di 3 mesi'];
    private const OLD_NAMES = ['workspace' => 'ws', 'endorsement' => 'appr']; // indirizzi della versione precedente

    /** @param array<string, string[]> $values */
    private function __construct(private readonly array $values, public readonly string $fresh)
    {
    }

    public static function fromRequest(Request $request): self
    {
        $values = [];
        foreach (self::MULTI as $key) {
            $raw = $request->query($key);
            $old = array_search($key, self::OLD_NAMES, true);
            if ($raw === null && $old !== false) {
                $raw = $request->query($old);
            }
            $list = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? [$raw] : []);
            $list = array_values(array_unique(array_filter(
                array_map(static fn ($v) => is_string($v) ? mb_substr(trim($v), 0, 200) : '', $list),
                static fn ($v) => $v !== '' && ($key !== 'area' || $v === 'none' || ctype_digit($v))
            )));
            if ($key === 'tag') {
                $list = array_values(array_unique(array_map('mb_strtolower', $list)));
            }
            $values[$key] = array_slice($list, 0, 30);
        }
        $fresh = (string) $request->query('fresh', '');
        return new self($values, isset(self::FRESH[$fresh]) ? $fresh : '');
    }

    /** @return string[] */
    public function get(string $key): array
    {
        return $this->values[$key] ?? [];
    }

    public function has(string $key, string $value): bool
    {
        return in_array($value, $this->get($key), true);
    }

    public function isEmpty(): bool
    {
        return $this->fresh === '' && !array_filter($this->values);
    }

    /** Parametri per costruire indirizzi (url() scarta quelli vuoti). */
    public function params(): array
    {
        return $this->values + ['fresh' => $this->fresh];
    }

    /** Gli stessi filtri senza un valore (o senza tutto un filtro): per la × dei filtri attivi. */
    public function without(string $key, ?string $value = null): self
    {
        if ($key === 'fresh') {
            return new self($this->values, '');
        }
        $values = $this->values;
        $values[$key] = $value === null ? [] : array_values(array_diff($values[$key] ?? [], [$value]));
        return new self($values, $this->fresh);
    }

    /** Traduzione per CatalogRepository: categorie espanse con le sottocategorie. */
    public function toQuery(AreaRepository $areas): array
    {
        $ids = [];
        foreach ($this->get('area') as $a) {
            if ($a !== 'none') {
                $ids = array_merge($ids, $areas->subtreeIds((int) $a));
            }
        }
        return array_filter([
            'area_ids'  => array_values(array_unique($ids)),
            'area_none' => $this->has('area', 'none'),
            'ws'        => $this->get('ws'),
            'kind'      => $this->get('kind'),
            'appr'      => $this->get('appr'),
            'tags'      => $this->get('tag'),
            'fresh'     => $this->fresh,
        ]);
    }
}
