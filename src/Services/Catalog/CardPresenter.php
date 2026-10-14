<?php
declare(strict_types=1);

namespace Ramingo\Services\Catalog;

use Ramingo\Domain\Source;
use Ramingo\Domain\Sourced;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Auth\AccessService;
use Ramingo\Services\Auth\CurrentUser;

/**
 * Prepara le "schede" del catalogo (usate sia dal catalogo sia dalla ricerca):
 * descrizione con provenienza, tag, stato di accesso. Le viste ricevono dati pronti.
 */
final class CardPresenter
{
    public function __construct(
        private readonly AnnotationRepository $annotations,
        private readonly TagRepository $tags,
        private readonly AccessService $access,
        private readonly int $staleDays = 7,   // oltre, la data dei dati è evidenziata
    ) {
    }

    /** @param array $rows righe di CatalogRepository */
    public function present(array $rows, CurrentUser $user): array
    {
        $byType = [];
        foreach ($rows as $r) {
            $byType[$r['entity_type']][] = $r['id'];
        }
        $notes = $tags = [];
        foreach ($byType as $type => $ids) {
            $notes[$type] = $this->annotations->currentFor($type, $ids);
            $tags[$type]  = $this->tags->forEntities($type, $ids);
        }
        $access = $this->access->states($user, $rows);

        return array_map(function (array $r) use ($notes, $tags, $access): array {
            $n = $notes[$r['entity_type']][$r['id']] ?? [];
            $key = $r['entity_type'] . ':' . $r['id'];
            // array_merge: 'description' deve sostituire il testo grezzo con il valore + provenienza
            return array_merge($r, [
                'key'         => $key,
                'detail_url'  => url($r['entity_type'] . '/' . $r['id']),
                'description' => self::bestDescription($r['description'] ?? null, $n),
                'tags'        => array_values(array_filter($tags[$r['entity_type']][$r['id']] ?? [], static fn ($t) => $t['source'] === 'manual')),
                'access'      => $access[$key] ?? AccessService::UNKNOWN,
                // data dei DATI (solo report: le dashboard non hanno un modello proprio)
                'freshness'   => $r['entity_type'] === 'report' ? Freshness::of($r, $this->staleDays) : null,
            ]);
        }, $rows);
    }

    /**
     * Descrizione mostrata ai colleghi: scritta a mano, altrimenti quella di Power BI.
     * Le interpretazioni automatiche (regole o AI) NON compaiono qui finché qualcuno non le approva.
     */
    public static function bestDescription(?string $pbiDescription, array $notes): ?Sourced
    {
        return Sourced::firstFilled(
            $notes['description'][Source::Manual->value] ?? null,
            $pbiDescription ? Sourced::pbi($pbiDescription) : null,
        );
    }
}
