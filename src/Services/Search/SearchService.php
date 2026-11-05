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
    private const FIELD_LABELS = ['title' => 'titolo', 'keywords' => 'categoria, tag e KPI', 'body' => 'descrizioni e pagine', 'tech' => 'nomi tecnici dei dati'];

    /**
     * Ordine dei risultati, a gradini (per ogni parola cercata conta il punto migliore in cui compare):
     *   nel nome 30  >  categoria, tag, KPI 10  >  descrizioni e pagine 3  >  solo nomi tecnici 0.
     * I gradini sono scelti perché un gradino più alto vinca sempre sulla somma di quelli sotto
     * (30 > 10 + 3). Il punteggio BM25 di SQLite (tra 0 e circa 1,3 per parola) serve solo a
     * ordinare dentro lo stesso gradino: da solo non distingue il nome dal resto, perché FTS5
     * moltiplica le occorrenze per il peso della colonna ma poi le satura.
     */
    private const FIELD_RANK = ['titolo' => 30, 'categoria, tag e KPI' => 10, 'descrizioni e pagine' => 3, 'nomi tecnici dei dati' => 0];

    public function __construct(private readonly QueryInterpreter $interpreter, private readonly SearchRepository $index)
    {
    }

    /**
     * @return array{concepts: array, mode: string, hits: array<string, array{score:float, why:array}>, corrected: array<string,string>}
     *         hits indicizzati per "tipo:id", già ordinati (vedi FIELD_RANK)
     */
    public function search(string $query, int $limit = 100, bool $correct = true): array
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
        $wanted = Text::normalize($query);
        foreach ($rows as $row) {
            $why = $this->explain($row, $concepts);
            // «debole»: trovato SOLO tra i nomi tecnici del modello dati (es. una colonna «fatturazione»)
            $weak = $why && !array_filter($why, static fn ($w) => $w['fields'] !== ['nomi tecnici dei dati']);
            $rank = 0;
            foreach ($why as $w) {
                $rank += max(array_map(static fn ($f) => self::FIELD_RANK[$f] ?? 0, $w['fields']));
            }
            $hits[$row['entity_key']] = [
                'score' => 100 * count($why)                            // più parole trovate = più in alto
                         + $rank                                        // poi dove compaiono (nome prima di tutto)
                         + ($wanted !== '' && $row['title'] === $wanted ? 5 : 0) // nome identico alla ricerca
                         + (float) $row['score']                        // a parità, il calcolo BM25
                         - ($weak ? 1000 : 0),                          // i deboli in fondo
                'why'   => $why,
                'weak'  => $weak,
            ];
        }
        uasort($hits, static fn ($a, $b) => $b['score'] <=> $a['score']);

        // Nessun risultato: forse un errore di battitura («carburnate»). Si corregge con la parola
        // più simile tra quelle presenti nel catalogo e si cerca di nuovo, dicendolo all'utente.
        if (!$hits && $correct && ($fixed = $this->corrections($concepts))) {
            $retry = $this->search(implode(' ', array_map(static fn ($t) => $fixed[$t] ?? $t, Text::tokens($query))), $limit, false);
            if ($retry['hits']) {
                return array_merge($retry, ['corrected' => $fixed]);
            }
        }
        return ['concepts' => $concepts, 'mode' => $mode, 'hits' => $hits, 'corrected' => []];
    }

    /**
     * Parole della ricerca che non compaiono da nessuna parte nel catalogo, con la parola del catalogo
     * più vicina (distanza di modifica 1, o 2 per le parole lunghe; a parità, la più frequente).
     * @return array<string, string> parola scritta => parola corretta
     */
    private function corrections(array $concepts): array
    {
        $vocab = $this->index->vocabulary();
        $fixed = [];
        foreach ($concepts as $c) {
            $word = $c['input'];
            if (strlen($word) < 4) {
                continue;
            }
            foreach ($c['stems'] as $stem) {
                foreach ($vocab as $v => $n) {
                    if (str_starts_with((string) $v, $stem)) {
                        continue 3; // la parola (o un suo sinonimo) esiste: non è un errore
                    }
                }
            }
            $max = strlen($word) >= 7 ? 2 : 1;
            $best = null;
            $bestScore = [PHP_INT_MAX, 0];
            foreach ($vocab as $v => $n) {
                $v = (string) $v;
                if (abs(strlen($v) - strlen($word)) > $max) {
                    continue;
                }
                $d = levenshtein($word, $v);
                if ($d <= $max && [$d, -$n] < $bestScore) {
                    $best = $v;
                    $bestScore = [$d, -$n];
                }
            }
            if ($best !== null) {
                $fixed[$word] = $best;
            }
        }
        return $fixed;
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
