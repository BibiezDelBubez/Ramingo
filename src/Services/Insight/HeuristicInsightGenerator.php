<?php
declare(strict_types=1);

namespace Ramingo\Services\Insight;

use Ramingo\Services\Search\Text;

/**
 * Interpretazioni basate su regole trasparenti:
 *  - sintesi testuale costruita da pagine e misure
 *  - KPI probabili = misure visibili con nomi "da KPI"
 *  - tag = concetti del vocabolario aziendale riconosciuti nel report
 */
final class HeuristicInsightGenerator implements InsightGenerator
{
    private const KPI_HINTS = [
        'totale', 'total', 'tot', 'fatturato', 'ricavi', 'revenue', 'margine', 'margin', 'ebitda', 'costo', 'cost',
        'quantita', 'qty', 'volume', 'numero', 'count', 'media', 'avg', 'average', 'perc', 'pct', 'yoy', 'ytd', 'mtd',
        'var', 'delta', 'budget', 'target', 'forecast', 'giorni', 'days', 'tasso', 'rate', 'oee', 'scarti', 'ordini',
    ];

    public function __construct(private readonly array $vocabulary)
    {
    }

    public function name(): string
    {
        return 'heuristic-v2';
    }

    /**
     * Testi pensati per chi USA il report: niente nomi tecnici.
     * Le tabelle compaiono solo se hanno un nome comprensibile nel glossario.
     */
    public function generate(array $ctx): array
    {
        $out = [];
        $glossary = $ctx['glossary'] ?? [];
        $label = static fn (string $kind, string $name): ?string =>
            ($glossary[$kind][mb_strtolower($name)]['label'] ?? '') ?: null;

        $measures = $ctx['measures'] ?? [];
        $pages    = array_column($ctx['pages'] ?? [], 'display_name');

        // KPI: misure con nome "da indicatore" oppure già tradotte nel glossario (quindi ritenute importanti)
        $kpis = [];
        foreach ($measures as $m) {
            $human = $label('measure', $m['name']);
            if ($human !== null || $this->looksLikeKpi($m['name'])) {
                $kpis[] = $human ?? $m['name'];
            }
        }
        $kpis = array_values(array_unique($kpis));
        if ($kpis) {
            $out['kpi'] = implode("\n", array_slice($kpis, 0, 12));
        }

        $tables = array_values(array_filter(array_map(
            static fn ($t) => $label('table', $t['name']),
            $ctx['tables'] ?? []
        )));

        $parts = [];
        if ($pages) {
            $parts[] = sprintf('Pagine: %s.', implode(', ', array_slice($pages, 0, 8)));
        }
        if ($tables) {
            $parts[] = 'Contiene dati su: ' . implode(', ', array_slice($tables, 0, 6)) . '.';
        }
        if ($kpis) {
            $parts[] = 'Indicatori principali: ' . implode(', ', array_slice($kpis, 0, 5)) . '.';
        }
        if ($parts) {
            $out['summary'] = implode(' ', $parts);
        }

        $out['tags'] = $this->conceptTags(implode(' ', [
            $ctx['name'] ?? '', $ctx['description'] ?? '', implode(' ', $pages), implode(' ', $tables),
            implode(' ', array_column($measures, 'name')), implode(' ', array_column($ctx['tables'] ?? [], 'name')),
        ]));

        return $out;
    }

    private function looksLikeKpi(string $measureName): bool
    {
        $tokens = Text::tokens((string) preg_replace('~([a-z])([A-Z])~', '$1 $2', $measureName));
        return (bool) array_intersect($tokens, self::KPI_HINTS) || str_contains($measureName, '%');
    }

    /** Concetti del vocabolario (primo termine del gruppo) trovati nel testo. */
    private function conceptTags(string $text): array
    {
        $tokens = array_flip(array_map([Text::class, 'stem'], Text::tokens((string) preg_replace('~([a-z])([A-Z])~', '$1 $2', $text))));
        $tags = [];
        foreach ($this->vocabulary['groups'] ?? [] as $group) {
            foreach ($group as $word) {
                if (isset($tokens[Text::stem($word)])) {
                    $tags[] = $group[0];
                    break;
                }
            }
        }
        return $tags;
    }
}
