<?php
declare(strict_types=1);

namespace Ramingo\Services\Search;

/**
 * Trasforma una domanda in linguaggio naturale ("voglio vedere le vendite per regione")
 * in concetti da cercare, ciascuno espanso con i sinonimi del vocabolario aziendale.
 *
 * Il risultato è un'INTERPRETAZIONE AUTOMATICA (Source::Auto) e viene mostrata
 * all'utente così com'è, perché possa capire e correggere la ricerca.
 */
final class QueryInterpreter
{
    /** @var array<int, string[]> */
    private array $groups;
    /** @var array<string, true> */
    private array $stopwords;

    public function __construct(array $vocabulary)
    {
        $this->groups    = $vocabulary['groups'] ?? [];
        $this->stopwords = array_fill_keys(array_map([Text::class, 'normalize'], $vocabulary['stopwords'] ?? []), true);
    }

    /**
     * @return array<int, array{input:string, label:string, stems:string[], expanded:bool}>
     */
    public function interpret(string $query): array
    {
        $concepts = [];
        $seen = [];
        foreach (Text::tokens($query) as $token) {
            if (isset($this->stopwords[$token]) || strlen($token) < 2) {
                continue;
            }
            $stem = Text::stem($token);
            if (isset($seen[$stem])) {
                continue;
            }
            $group = $this->groupFor($stem);
            $stems = $group ? array_values(array_unique(array_merge([$stem], array_map([Text::class, 'stem'], $this->flattenWords($group))))) : [$stem];
            foreach ($stems as $s) {
                $seen[$s] = true;
            }
            $concepts[] = [
                'input'    => $token,
                'label'    => $group[0] ?? $token,
                'stems'    => array_values(array_filter($stems, static fn ($s) => $s !== '')),
                'expanded' => $group !== null,
            ];
        }
        return $concepts;
    }

    private function groupFor(string $stem): ?array
    {
        foreach ($this->groups as $group) {
            foreach ($this->flattenWords($group) as $word) {
                if (Text::stem($word) === $stem) {
                    return $group;
                }
            }
        }
        return null;
    }

    /**
     * "risorse umane" -> ["risorse", "umane"]: le espressioni diventano parole singole.
     * Dalle espressioni composte si scartano i frammenti corti ("sell-in" -> "sell", non "in"),
     * che altrimenti troverebbero corrispondenze ovunque.
     */
    private function flattenWords(array $group): array
    {
        $out = [];
        foreach ($group as $w) {
            $tokens = Text::tokens($w);
            foreach ($tokens as $t) {
                if (count($tokens) === 1 ? strlen($t) >= 2 : strlen($t) >= 4) {
                    $out[] = $t;
                }
            }
        }
        return $out;
    }
}
