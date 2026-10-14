<?php
/** @var array $cards  @var ?array $groups  @var int $total  @var array $filters  @var string $sort */
?>
<?php if ($demo): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="fa-solid fa-flask"></i> <strong>Dati di esempio:</strong> report, persone e workspace sono inventati.
        <?php if ($user->isAdmin()): ?><a href="<?= url('admin/powerbi') ?>">Collega il tuo Power BI</a><?php endif ?>
    </div>
<?php endif ?>

<?= $view->partial('partials/filters', get_defined_vars()) ?>

<?php if (!$cards): ?>
    <div class="rg-empty">
        <p><strong>Nessun report con questi filtri.</strong></p>
        <p><a href="<?= e(url('', ['sort' => $sort])) ?>">Togli tutti i filtri</a> oppure scrivi cosa cerchi nella ricerca qui sopra.</p>
    </div>
<?php elseif ($groups !== null): ?>
    <?php foreach ($groups as $g): ?>
        <section class="rg-group">
            <h2 class="rg-group-title"><i class="<?= e($g['icon']) ?>" aria-hidden="true"></i><?= e($g['name']) ?><span class="rg-n"><?= count($g['cards']) ?></span></h2>
            <div class="rg-grid"><?php foreach ($g['cards'] as $c) echo $view->partial('partials/item_card', ['c' => $c]) ?></div>
        </section>
    <?php endforeach ?>
<?php else: ?>
    <div class="rg-grid"><?php foreach ($cards as $c) echo $view->partial('partials/item_card', ['c' => $c]) ?></div>
<?php endif ?>

<?php if ($pages > 1): ?>
    <nav class="rg-pager" aria-label="Pagine">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e(url('', array_merge($filters, ['sort' => $sort, 'page' => $i]))) ?>"
               <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor ?>
    </nav>
<?php endif ?>
