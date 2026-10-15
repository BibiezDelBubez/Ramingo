<?php
/** Un report nella vista a elenco compatto. @var array $c scheda preparata da CardPresenter */
$kinds = Ramingo\Models\CatalogRepository::KINDS;
$icons = ['report' => 'fa-solid fa-chart-column', 'paginated' => 'fa-solid fa-file-lines', 'dashboard' => 'fa-solid fa-gauge-high'];
$desc  = $c['description'] !== null && !$c['description']->isEmpty() ? mb_strimwidth((string) $c['description']->value, 0, 160, '…') : '';
?>
<li class="rg-row kind-<?= e($c['kind']) ?>">
    <i class="<?= e($icons[$c['kind']]) ?> rg-row-ico" title="<?= e($kinds[$c['kind']]) ?>" aria-hidden="true"></i>
    <div class="rg-row-main">
        <a class="rg-row-title" href="<?= e($c['detail_url']) ?>"><?= e($c['name']) ?></a>
        <?php if ($desc !== ''): ?><span class="rg-row-desc"><?= e($desc) ?></span><?php endif ?>
        <?php if ($c['tags']): ?><span class="rg-row-tags"><?= $view->partial('partials/tags', ['tags' => $c['tags']]) ?></span><?php endif ?>
    </div>
    <div class="rg-row-meta">
        <?php if ($c['freshness']): ?><?= $view->partial('partials/freshness', ['f' => $c['freshness']]) ?><?php endif ?>
        <span title="Workspace di Power BI in cui è salvato"><i class="fa-solid fa-cloud" aria-hidden="true"></i><?= e($c['workspace_name']) ?></span>
    </div>
    <a class="rg-open" href="<?= e($c['app_url'] ?: $c['web_url']) ?>" target="_blank" rel="noopener" title="<?= $c['app_url'] ? "Apri nell'app" : 'Apri in Power BI' ?>">
        Apri <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
</li>
