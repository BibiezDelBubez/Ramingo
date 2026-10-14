<?php
declare(strict_types=1);

namespace Ramingo\Services\Insight;

/**
 * Produce INTERPRETAZIONI AUTOMATICHE (Source::Auto) per un report.
 * Oggi: HeuristicInsightGenerator (regole, nessun servizio esterno).
 * Domani: un'implementazione basata su un modello linguistico aziendale
 * (es. Azure OpenAI nel vostro tenant) può sostituirla senza toccare il resto.
 */
interface InsightGenerator
{
    /** Identificativo salvato con ogni interpretazione (tracciabilità). */
    public function name(): string;

    /**
     * @param array $context report, pagine, misure, tabelle già caricate
     * @return array{summary?:string, kpi?:string, tags?:string[]}
     */
    public function generate(array $context): array;
}
