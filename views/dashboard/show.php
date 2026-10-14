<?php
/** @var array $d  @var string $access  @var array $notes  @var array $tags  @var array $owners  @var array $tiles  @var bool $canEdit */
use Ramingo\Domain\Source;
?>
<nav class="small mb-2"><a href="<?= url() ?>">Catalogo</a> / <?= e($d['area_name'] ?? 'Da classificare') ?> / <?= e($d['workspace_name']) ?></nav>
<div class="rg-hero d-flex flex-wrap justify-content-between gap-3">
    <div>
        <h1 class="h3 mb-1"><?= e($d['name']) ?> <?= $view->partial('partials/source_badge', ['source' => Source::PowerBi]) ?></h1>
        <div class="text-secondary">Dashboard · workspace <?= e($d['workspace_name']) ?></div>
        <div class="mt-2"><?= $view->partial('partials/tags', ['tags' => $tags]) ?></div>
    </div>
    <div class="text-end">
        <div class="mb-2"><?= $view->partial('partials/access_badge', ['state' => $access]) ?></div>
        <?= $view->partial('partials/open_buttons', ['item' => $d]) ?>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="rg-section" id="descrizioni">
            <h2 class="h5">Descrizione</h2>
            <?= $view->partial('partials/descriptions', ['notes' => $notes]) ?>
            <?php if ($canEdit): ?><?= $view->partial('partials/annotation_form', ['entityType' => 'dashboard', 'entityId' => $d['id'], 'notes' => $notes, 'tags' => $tags, 'tagsInUse' => $tagsInUse, 'csrf' => $csrf]) ?><?php endif ?>
        </section>
        <section class="rg-section">
            <h2 class="h5">Riquadri <?= $view->partial('partials/source_badge', ['source' => Source::PowerBi]) ?></h2>
            <ul>
            <?php foreach ($tiles as $t): ?>
                <li><?= e($t['title'] ?: '(senza titolo)') ?>
                    <?php if ($t['report_name']): ?><span class="small text-secondary">— da <a href="<?= url('report/' . $t['report_id']) ?>"><?= e($t['report_name']) ?></a></span><?php endif ?></li>
            <?php endforeach ?>
            </ul>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="rg-section">
            <h2 class="h5">Scheda tecnica</h2>
            <?= $view->partial('partials/pbi_facts', ['facts' => [
                'Workspace'   => $d['workspace_name'],
                'Proprietari' => array_map(static fn ($o) => ($o['display_name'] ?? $o['email']) . ' · ' . $o['access_right'], $owners),
                'Visto in Power BI il' => fmt_date($d['last_seen_at'], true),
            ]]) ?>
        </section>
    </div>
</div>
