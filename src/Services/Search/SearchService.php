<?php
declare(strict_types=1);

namespace Ramingo\Services\Search;

use Ramingo\Models\SearchRepository;

/**
 * Ricerca "intelligente":
 *  1. interpreta la domanda in concetti + sinonimi (QueryInterpreter)
 *  2. cerca prima i report che contengono TUTTI i concetti, poi ripiega su ALMENO UNO
 *  3. spiega perché ogni risultato è stato trovato (concetto -> dove compare)
 */
final class SearchService
{
    private const FIELD_LABELS = ['title' => 'titolo', 'keywords' => 'area, tag e KPI', 'body' => 'contenuti'];

    public function __construct(private readonly QueryInterpreter $interpreter, private readonly SearchRepository $index)
    {
    }

    /**
     * @return array{concepts: array, mode: string, hits: array<string, array{score:float, why:array}>}
     *         hits indicizzati per "tipo:id", già ordinati per pertinenza
     */
    public function search(string $query, int $limit = 100): array
    {
        $concepts = $this->interpreter->interpret($query);
        $groups   = array_column($concepts, 'stems');

        $mode = 'all';
        $rows = $this->index->query($groups, true, $limit);
        if (!$rows && count($groups) > 1) {
            $mode = 'any';
            $rows = $this->index->query($groups, false, $limit);
        }

        $hits = [];
        foreach ($rows as $row) {
            $why = $this->explain($row, $concepts);
            $hits[$row['entity_key']] = [
                'score'   => (float) $row['score'] + 100 * count($why), // più concetti trovati = più in alto
                'why'     => $why,
            ];
        }
        uasort($hits, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['concepts' => $concepts, 'mode' => $mode, 'hits' => $hits];
    }

    /** @return array<int, array{concept:string, fields:string[]}> */
    private function explain(array $row, array $concepts): array
    {
        $why = [];
        foreach ($concepts as $c) {
            $fields = [];
            foreach (self::FIELD_LABELS as $col => $label) {
                foreach ($c['stems'] as $stem) {
                    if (preg_match('~\b' . preg_quote($stem, '~') . '~', (string) $row[$col])) {
                        $fields[] = $label;
                        break;
                    }
                }
            }
            if ($fields) {
                $why[] = ['concept' => $c['label'], 'fields' => $fields];
            }
        }
        return $why;
    }
}
