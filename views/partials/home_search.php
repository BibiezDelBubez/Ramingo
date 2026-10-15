<?php
/**
 * Testata della pagina iniziale: la domanda e la ricerca grande.
 * @var string $q  @var array $counts  @var ?array $lastSync  @var array $examples
 */
$reports    = ($counts['report'] ?? 0) + ($counts['paginated'] ?? 0);
$dashboards = $counts['dashboard'] ?? 0;
$what = array_filter([
    $reports ? $reports . ($reports === 1 ? ' report' : ' report') : null,
    $dashboards ? $dashboards . ($dashboards === 1 ? ' dashboard' : ' dashboard') : null,
]);
?>
<section class="rg-home">
    <div class="rg-wide rg-home-inner">
        <h1 class="rg-home-title">Quale report ti serve?</h1>
        <form class="rg-home-search" action="<?= url('cerca') ?>" method="get" role="search" autocomplete="off" data-suggest data-home-search>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= e($q) ?>" aria-label="Cerca nei report"
                   placeholder="Scrivilo con parole tue">
            <button class="rg-home-go">Cerca</button>
            <div class="rg-suggest" hidden></div>
        </form>
        <div class="rg-home-below">
            <?php if ($examples): ?>
                <p class="rg-examples">Prova con
                    <?php foreach ($examples as $ex): ?><a href="<?= e(url('cerca', ['q' => $ex])) ?>"><?= e($ex) ?></a><?php endforeach ?>
                </p>
            <?php endif ?>
            <p class="rg-home-meta">
                <?php if ($lastSync): ?>
                    <?= e(implode(' e ', $what) ?: 'Nessun contenuto') ?> da Power BI, letti il <?= fmt_date($lastSync['finished_at'], true) ?>
                <?php else: ?>
                    Il catalogo non è ancora stato letto da Power BI.
                <?php endif ?>
            </p>
        </div>
    </div>
</section>
