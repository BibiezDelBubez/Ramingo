<?php
/** Pulsante «Spiega con l'AI» con trasparenza su fornitore e dati inviati. @var ?array $ai  @var string $reportId */
if ($ai === null) return;
?>
<form method="post" action="<?= url('ai/report/' . $reportId . '/spiega') ?>" class="rg-ai-box" data-busy>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="btn btn-sm btn-ai"><i class="fa-solid fa-wand-magic-sparkles"></i> <span>Spiega con l'AI</span></button>
    <span class="small text-secondary">
        Usa <strong><?= e($ai['label']) ?></strong> (<?= $ai['owner'] === 'personal' ? 'tua chiave' : 'chiave aziendale' ?>).
        Invia solo metadati del report: nomi, descrizioni, pagine, tabelle, misure. Nessun dato numerico.
        Il risultato è salvato come <?= $view->partial('partials/source_badge', ['source' => Ramingo\Domain\Source::Auto]) ?>.
    </span>
</form>
