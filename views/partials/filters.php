<?php
/**
 * Barra dei filtri del catalogo: categorie come scelte principali, gli altri filtri a tendina.
 * Un filtro compare solo se offre almeno due scelte (o se è già attivo).
 * @var array $facets  @var array $filters  @var string $sort  @var array $sorts  @var array $labels filtri attivi  @var int $total
 */
$kinds = Ramingo\Models\CatalogRepository::KINDS;
$link  = static fn (array $change) => url('', array_merge($filters, ['sort' => $sort], $change, ['page' => null]));
$worth = static fn (array $options, string $key) => count($options) > 1 || ($filters[$key] ?? '') !== '';

// Categorie: primo livello come scelte principali; le sottocategorie di quella aperta in una seconda riga
$tree  = $facets['areaTree'];
$roots = [];
$subs  = [];
$root  = null;
foreach ($tree as $a) {
    if ((int) $a['depth'] === 0) {
        $root = (string) $a['value'];
        $roots[] = $a;
    } else {
        $subs[$root][] = $a;
    }
}
$openRoot = null;
foreach ($tree as $a) {
    if ((int) $a['depth'] === 0) {
        $root = (string) $a['value'];
    }
    if ((string) $a['value'] === $filters['area']) {
        $openRoot = $root;
    }
}
$categorized = array_filter($roots, static fn ($a) => $a['value'] !== 'none');

$kindOptions = array_map(static fn ($k) => ['value' => $k['value'], 'label' => $kinds[$k['value']] ?? $k['value'], 'n' => $k['n']], $facets['kinds']);
$endorseOptions = array_map(static fn ($en) => ['value' => $en['value'], 'label' => $en['value'] === 'Certified' ? 'Certificati' : 'Promossi', 'n' => $en['n']], $facets['endorsements']);
$drop = static fn (array $args) => $view->partial('partials/filter_drop', $args + ['link' => $link, 'current' => $filters[$args['key']] ?? '']);
?>
<div class="rg-filterbar">
    <?php if ($categorized): ?>
        <nav class="rg-cats" aria-label="Categorie">
            <a class="rg-cat<?= $filters['area'] === '' ? ' active' : '' ?>" href="<?= e($link(['area' => null])) ?>">Tutte</a>
            <?php foreach ($roots as $a): ?>
                <a class="rg-cat<?= (string) $a['value'] === $openRoot ? ' active' : '' ?>" href="<?= e($link(['area' => $a['value']])) ?>"
                   <?= (string) $a['value'] === $filters['area'] ? 'aria-current="page"' : '' ?>>
                    <i class="<?= e($a['icon'] ?? 'fa-solid fa-shapes') ?>" aria-hidden="true"></i><?= e($a['label']) ?><span class="rg-n"><?= (int) $a['n'] ?></span></a>
            <?php endforeach ?>
        </nav>
        <?php if ($openRoot !== null && !empty($subs[$openRoot])): ?>
            <nav class="rg-subcats" aria-label="Sottocategorie">
                <?php foreach ($subs[$openRoot] as $a): ?>
                    <a class="rg-subcat depth-<?= min(4, (int) $a['depth']) ?><?= (string) $a['value'] === $filters['area'] ? ' active' : '' ?>"
                       href="<?= e($link(['area' => $a['value']])) ?>"><?= e($a['label']) ?><span class="rg-n"><?= (int) $a['n'] ?></span></a>
                <?php endforeach ?>
            </nav>
        <?php endif ?>
    <?php elseif ($user->isAdmin()): ?>
        <p class="rg-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Qui compariranno le categorie (Commerciale, Contabilità…) quando avrai assegnato i workspace:
            <a href="<?= url('admin/categorie') ?>">assegna le categorie</a>. I colleghi non vedono questo avviso.</p>
    <?php endif ?>

    <div class="rg-tools">
        <?= $drop(['key' => 'workspace', 'label' => 'Workspace', 'icon' => 'fa-solid fa-cloud', 'options' => $facets['workspaces']]) ?>
        <?php if ($worth($kindOptions, 'kind')): ?>
            <?= $drop(['key' => 'kind', 'label' => 'Tipo', 'icon' => 'fa-regular fa-file', 'options' => $kindOptions, 'searchable' => false]) ?>
        <?php endif ?>
        <?php if ($endorseOptions || $filters['endorsement'] !== ''): ?>
            <?= $drop(['key' => 'endorsement', 'label' => 'Approvazione', 'icon' => 'fa-regular fa-circle-check', 'options' => $endorseOptions, 'searchable' => false]) ?>
        <?php endif ?>
        <?php if ($facets['tags'] || $filters['tag'] !== ''): ?>
            <?= $drop(['key' => 'tag', 'label' => 'Tag', 'icon' => 'fa-solid fa-hashtag', 'options' => $facets['tags']]) ?>
        <?php endif ?>

        <span class="rg-count-total"><?= (int) $total ?> <?= $total === 1 ? 'risultato' : 'risultati' ?>
            <?php if ($labels): ?><a class="rg-clear" href="<?= e(url('', ['sort' => $sort])) ?>">Togli i filtri</a><?php endif ?></span>

        <form method="get" action="<?= url() ?>" class="rg-sortform">
            <?php foreach ($filters as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; endforeach ?>
            <label class="visually-hidden" for="rg-sort">Ordina</label>
            <select id="rg-sort" name="sort" class="form-select form-select-sm" data-autosubmit>
                <?php foreach ($sorts as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === $sort ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
            </select>
            <noscript><button class="btn btn-sm btn-outline-secondary">Ordina</button></noscript>
        </form>
    </div>

</div>
