<?php
declare(strict_types=1);

namespace Ramingo\Services\Insight;

use Ramingo\Domain\Source;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\DatasetRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\TagRepository;

/** Applica un InsightGenerator a tutti i report e salva i risultati come Source::Auto. */
final class InsightService
{
    public function __construct(
        private readonly InsightGenerator $generator,
        private readonly ReportRepository $reports,
        private readonly DatasetRepository $datasets,
        private readonly AnnotationRepository $annotations,
        private readonly TagRepository $tags,
        private readonly \Ramingo\Models\GlossaryRepository $glossary,
    ) {
    }

    public function refreshAll(): int
    {
        $changed = 0;
        foreach ($this->reports->activeIds() as $id) {
            $changed += $this->refreshReport($id) ? 1 : 0;
        }
        return $changed;
    }

    public function refreshReport(string $id): bool
    {
        $r = $this->reports->find($id);
        if (!$r) {
            return false;
        }
        $usage = \Ramingo\Services\Catalog\ReportUsage::fromVisuals($this->reports->visualsByPage($id));
        $ctx = $r + [
            'pages'    => $this->reports->pages($id),
            'measures' => $r['dataset_id'] ? array_values(array_filter($this->datasets->visibleMeasures($r['dataset_id']), static fn ($m) => $usage->usesMeasure($m['name']))) : [],
            'tables'   => $r['dataset_id'] ? array_filter($this->datasets->schema($r['dataset_id']), static fn ($t) => !$t['is_hidden'] && $usage->usesTable($t['name'])) : [],
            'glossary' => $this->glossary->map(),
        ];
        $result = $this->generator->generate($ctx);

        $changed = false;
        foreach (['summary', 'kpi'] as $field) {
            $changed = $this->annotations->save('report', $id, $field, $result[$field] ?? '', Source::Auto, 'Ramingo', $this->generator->name()) || $changed;
        }
        // I tag automatici si sono rivelati poco affidabili: non si generano più (e si cancellano i vecchi)
        $this->tags->replace('report', $id, [], Source::Auto);
        return $changed;
    }
}
