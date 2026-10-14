<?php
/** @var array $c scheda preparata da CardPresenter (+ eventuale 'why' dalla ricerca) */
$kinds = Ramingo\Models\CatalogRepository::KINDS;
$icons = ['report' => 'fa-solid fa-chart-column', 'paginated' => 'fa-solid fa-file-lines', 'dashboard' => 'fa-solid fa-gauge-high'];
$open  = $c['app_url'] ?: $c['web_url'];
?>
<article class="rg-card kind-<?= e($c['kind']) ?>">
    <div class="rg-card-top">
        <span class="rg-kind"><i class="<?= e($icons[$c['kind']]) ?>" aria-hidden="true"></i><?= e($kinds[$c['kind']]) ?></span>
        <?php if ($c['endorsement']): ?>
            <span class="rg-endorse <?= $c['endorsement'] === 'Certified' ? 'certified' : 'promoted' ?>" title="Approvazione impostata in Power BI">
                <i class="fa-solid <?= $c['endorsement'] === 'Certified' ? 'fa-badge-check' : 'fa-thumbs-up' ?>" aria-hidden="true"></i>
                <?= $c['endorsement'] === 'Certified' ? 'Certificato' : 'Promosso' ?>
            </span>
        <?php endif ?>
    </div>
    <h3 class="rg-card-title"><a href="<?= e($c['detail_url']) ?>"><?= e($c['name']) ?></a></h3>
    <div class="rg-card-desc"><?= $view->partial('partials/sourced', ['s' => $c['description'], 'empty' => 'Nessuna descrizione.']) ?></div>
    <?php if (!empty($c['why'])): ?>
        <div class="rg-why">
            <?= $view->partial('partials/source_badge', ['source' => Ramingo\Domain\Source::Auto]) ?>
            Trovato perché:
            <?php foreach ($c['why'] as $w): ?>
                <span><strong><?= e($w['concept']) ?></strong> in <?= e(implode(', ', $w['fields'])) ?></span>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <?php if ($c['tags']): ?><div class="rg-card-tags"><?= $view->partial('partials/tags', ['tags' => $c['tags']]) ?></div><?php endif ?>
    <div class="rg-card-meta">
        <span title="Workspace di Power BI in cui è salvato"><i class="fa-solid fa-cloud" aria-hidden="true"></i><?= e($c['workspace_name']) ?></span>
        <?php if ($c['freshness']): ?><?= $view->partial('partials/freshness', ['f' => $c['freshness']]) ?><?php endif ?>
    </div>
    <div class="rg-card-foot">
        <?= $view->partial('partials/access_badge', ['state' => $c['access']]) ?>
        <a class="rg-open" href="<?= e($open) ?>" target="_blank" rel="noopener">
            <?= $c['app_url'] ? "Apri nell'app" : 'Apri in Power BI' ?> <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
    </div>
</article>
