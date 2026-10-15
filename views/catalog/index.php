<?php
/**
 * Catalogo e risultati di ricerca: colonna filtri a sinistra, elenco al centro.
 * @var array $groups  @var int $total  @var string $sort  @var array $sorts  @var string $viewMode  @var array $active
 * @var Ramingo\Services\Catalog\CatalogFilters $filters  @var callable $link  @var array $keep  @var string $here
 */
$from = ($page - 1) * $pageSize + 1;
$to   = min($total, $page * $pageSize);
$clear = url($here, array_filter(['q' => $keep['q'] ?? '', 'view' => $keep['view'] ?? '']));
$viewUrl = static fn (string $v) => $link($filters, ['view' => $v, 'page' => $page > 1 ? $page : null]);
?>
<?php if ($demo): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="fa-solid fa-flask"></i> <strong>Dati di esempio:</strong> report, persone e workspace sono inventati.
        <?php if ($user->isAdmin()): ?><a href="<?= url('admin/powerbi') ?>">Collega il tuo Power BI</a><?php endif ?>
    </div>
<?php endif ?>

<div class="rg-browse<?= $sideHidden ? ' side-hidden' : '' ?>" data-browse>
    <aside class="rg-browse-side"><?= $view->partial('partials/sidebar', get_defined_vars()) ?></aside>

    <section class="rg-browse-main" aria-label="Risultati">
        <?php if ($q !== ''): ?><?= $view->partial('partials/search_head', get_defined_vars()) ?><?php endif ?>

        <div class="rg-toolbar">
            <button type="button" class="rg-side-show" data-side-show title="Mostra i filtri">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i> Filtri<?= $active ? ' (' . count($active) . ')' : '' ?></button>
            <div class="rg-toolbar-count">
                <strong><?= (int) $total ?></strong> <?= $total === 1 ? 'risultato' : 'risultati' ?><?= $q !== '' ? ' per «' . e($q) . '»' : '' ?>
            </div>
            <form method="get" action="<?= url($here) ?>" class="rg-sortform">
                <?= $view->partial('partials/hidden_params', ['params' => $filters->params() + array_diff_key($keep, ['sort' => 1])]) ?>
                <label class="visually-hidden" for="rg-sort">Ordina</label>
                <select id="rg-sort" name="sort" class="form-select form-select-sm" data-autosubmit>
                    <?php foreach ($sorts as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === $sort ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
                </select>
                <noscript><button class="btn btn-sm btn-outline-secondary">Ordina</button></noscript>
            </form>
            <div class="rg-viewtoggle" role="group" aria-label="Vista">
                <a href="<?= e($viewUrl('grid')) ?>" class="<?= $viewMode === 'grid' ? 'active' : '' ?>" aria-pressed="<?= $viewMode === 'grid' ? 'true' : 'false' ?>" title="Schede"><i class="fa-solid fa-grip" aria-hidden="true"></i><span class="visually-hidden">Schede</span></a>
                <a href="<?= e($viewUrl('list')) ?>" class="<?= $viewMode === 'list' ? 'active' : '' ?>" aria-pressed="<?= $viewMode === 'list' ? 'true' : 'false' ?>" title="Elenco"><i class="fa-solid fa-list" aria-hidden="true"></i><span class="visually-hidden">Elenco</span></a>
            </div>
        </div>

        <?php if ($active): ?>
            <div class="rg-active" aria-label="Filtri attivi">
                <?php foreach ($active as $f): ?>
                    <a class="rg-active-chip" href="<?= e($f['url']) ?>" title="Togli questo filtro"><?= e($f['label']) ?> <i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="visually-hidden"> (togli)</span></a>
                <?php endforeach ?>
                <a class="rg-clear" href="<?= e($clear) ?>">Togli tutti i filtri</a>
            </div>
        <?php endif ?>

        <?php if ($pages > 1): ?>
            <p class="rg-pagenote">
                Mostro <?= $from ?>–<?= $to ?> di <?= (int) $total ?>, in ordine «<?= e(mb_strtolower($sorts[$sort])) ?>».
                Per trovare prima quello che cerchi, restringi con i filtri a sinistra.
            </p>
        <?php endif ?>

        <?php if (!$total): ?>
            <div class="rg-empty">
                <img class="rg-logo-fun" src="<?= asset('img/logo_animato.svg') ?>" alt="" width="72" height="69">
                <?php if ($q !== ''): ?>
                    <p><strong>Nessun report per «<?= e($q) ?>»<?= $active ? ' con questi filtri' : '' ?>.</strong></p>
                    <p><?= $active ? '<a href="' . e($clear) . '">Togli i filtri</a> oppure prova' : 'Prova' ?> con altre parole<?= $ai ? ', o chiedi all\'AI qui sopra' : '' ?>.</p>
                <?php else: ?>
                    <p><strong>Nessun report con questi filtri.</strong></p>
                    <p><a href="<?= e($clear) ?>">Togli tutti i filtri</a> oppure scrivi cosa cerchi nella ricerca qui sopra.</p>
                <?php endif ?>
            </div>
        <?php endif ?>

        <?php foreach ($groups as $g): if (!$g['cards'] && !$g['old'] && !$g['weak']) { continue; } ?>
            <section class="rg-group">
                <?php if ($g['name'] !== null): ?>
                    <h2 class="rg-group-title"><i class="<?= e($g['icon']) ?>" aria-hidden="true"></i><?= e($g['name']) ?><span class="rg-n"><?= count($g['cards']) + count($g['old']) + count($g['weak']) ?></span></h2>
                <?php endif ?>
                <?php foreach (['cards' => null, 'old' => 'Dati fermi da più di 3 mesi', 'weak' => null] as $part => $sep): if (!$g[$part]) { continue; } ?>
                    <?php
                    $list = $viewMode === 'list'
                        ? '<ul class="rg-list">' . implode('', array_map(static fn ($c) => $view->partial('partials/item_row', ['c' => $c]), $g[$part])) . '</ul>'
                        : '<div class="rg-grid">' . implode('', array_map(static fn ($c) => $view->partial('partials/item_card', ['c' => $c]), $g[$part])) . '</div>';
                    ?>
                    <?php if ($part === 'weak'): // chiusi, tranne quando i risultati pertinenti sono pochi ?>
                        <details class="rg-weak" <?= count($g['cards']) + count($g['old']) < 3 ? 'open' : '' ?>>
                            <summary>
                                <span><?= count($g['weak']) === 1 ? 'Un altro report trovato' : 'Altri ' . count($g['weak']) . ' report trovati' ?> solo tra i nomi tecnici dei dati</span>
                                <span class="rg-weak-note">tabelle, colonne e misure dei modelli: forse c'entrano poco</span>
                            </summary>
                            <?= $list ?>
                        </details>
                    <?php else: ?>
                        <?php if ($sep): ?><div class="rg-old-sep"><span><?= e($sep) ?></span></div><?php endif ?>
                        <?= $list ?>
                    <?php endif ?>
                <?php endforeach ?>
            </section>
        <?php endforeach ?>

        <?php if ($pages > 1): ?>
            <nav class="rg-pager" aria-label="Pagine">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e($link($filters, ['page' => $i > 1 ? $i : null])) ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                <?php endfor ?>
            </nav>
        <?php endif ?>
    </section>
</div>
