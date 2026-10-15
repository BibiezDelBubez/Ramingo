<?php
/**
 * Parte alta dei risultati di una ricerca: domanda all'AI, risposta, interpretazione, correzioni.
 * @var string $q  @var array $concepts  @var string $mode  @var array $corrected  @var ?array $ai
 */
use Ramingo\Domain\Source;

$aiAnswer ??= null;
$aiError ??= null;
?>
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
    <section class="rg-ai-answer mb-3">
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
        <?php if ($corrected): ?>
            <div class="small mt-1">Nessun report conteneva
                <?= implode(', ', array_map(static fn ($from, $to) => '«' . e($from) . '»: ho cercato «' . e($to) . '»', array_keys($corrected), $corrected)) ?>.</div>
        <?php endif ?>
        <?php if ($mode === 'any'): ?><div class="small mt-1">Nessun report contiene tutti i concetti: mostro quelli che ne contengono almeno uno.</div><?php endif ?>
    </div>
<?php endif ?>
