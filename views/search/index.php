<?php
/** @var string $q  @var array $concepts  @var string $mode  @var array $cards  @var ?array $ai */
use Ramingo\Domain\Source;

$aiAnswer ??= null;
$aiError ??= null;
?>
<h1 class="h3">Ricerca</h1>
<?php if ($q === ''): ?>
    <p class="text-secondary">Scrivi cosa ti serve nella barra in alto, anche con parole tue: «fatturato per agente», «giacenze magazzino», «turnover del personale».</p>
<?php else: ?>
    <?php if ($ai !== null): ?>
        <form method="post" action="<?= url('ai/chiedi') ?>" class="rg-ai-box mb-3" data-busy>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <button class="btn btn-sm btn-ai"><i class="fa-solid fa-wand-magic-sparkles"></i> <span>Chiedi all'AI quale report usare</span></button>
            <span class="small text-secondary">Con <strong><?= e($ai['label']) ?></strong>. Invia la domanda e nomi/descrizioni dei report trovati.</span>
        </form>
    <?php endif ?>

    <?php if ($aiError): ?><div class="alert alert-danger"><?= e($aiError) ?></div><?php endif ?>
    <?php if ($aiAnswer): ?>
        <section class="rg-ai-answer mb-4">
            <div class="mb-2"><?= $view->partial('partials/source_badge', ['source' => Source::Auto]) ?> <span class="small text-secondary"><?= e($aiAnswer['label']) ?> · da verificare</span></div>
            <p><?= nl2br(e($aiAnswer['answer'])) ?></p>
            <?php if ($aiAnswer['picks']): ?>
                <ol class="mb-0">
                    <?php foreach ($aiAnswer['picks'] as $p): ?>
                        <li><a href="<?= e($p['card']['detail_url']) ?>"><strong><?= e($p['card']['name']) ?></strong></a> — <?= e($p['reason']) ?></li>
                    <?php endforeach ?>
                </ol>
            <?php endif ?>
        </section>
    <?php endif ?>

    <?php if ($concepts): ?>
        <div class="rg-interpretation">
            <?= $view->partial('partials/source_badge', ['source' => Source::Auto]) ?>
            Ho interpretato la ricerca come:
            <?php foreach ($concepts as $i => $c): ?>
                <?= $i ? ($mode === 'all' ? '<em>e</em>' : '<em>oppure</em>') : '' ?>
                <span class="rg-concept" title="<?= e($c['expanded'] ? 'Cerco anche: ' . implode(', ', $c['stems']) : 'Nessun sinonimo nel vocabolario') ?>">
                    <?= e($c['label']) ?><?= $c['expanded'] ? ' <i class="fa-solid fa-arrows-split-up-and-left"></i>' : '' ?>
                </span>
            <?php endforeach ?>
            <?php if ($mode === 'any'): ?><div class="small mt-1">Nessun report contiene tutti i concetti: mostro quelli che ne contengono almeno uno.</div><?php endif ?>
        </div>
    <?php endif ?>
    <p class="text-secondary"><?= count($cards) ?> risultati per «<?= e($q) ?>».</p>
    <?php if ($cards): ?>
        <div class="rg-grid"><?php foreach ($cards as $c) echo $view->partial('partials/item_card', ['c' => $c]) ?></div>
    <?php else: ?>
        <p>Nessun risultato. Prova con altre parole<?= $ai ? ', chiedi all\'AI' : '' ?> oppure chiedi all'amministratore di aggiungere sinonimi al vocabolario aziendale.</p>
    <?php endif ?>
<?php endif ?>
