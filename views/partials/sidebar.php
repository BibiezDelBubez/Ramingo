<?php
/**
 * Colonna dei filtri (catalogo e ricerca). Un modulo GET: ogni spunta ricarica la pagina con i risultati filtrati.
 * Categoria, workspace, tipo: si sceglie in O. Tag: in E (i report devono averli tutti).
 * @var Ramingo\Services\Catalog\CatalogFilters $filters  @var array $facets  @var array $keep  @var string $here
 * @var array $active  @var callable $link
 */
use Ramingo\Services\Catalog\CatalogFilters;

$kinds = Ramingo\Models\CatalogRepository::KINDS;
$checked = static fn (string $key, string $value) => $filters->has($key, $value) ? 'checked' : '';

$areas = array_values(array_filter($facets['areaTree'], static fn ($a) => $a['n'] > 0 || $filters->has('area', $a['value'])));
$hasCategories = (bool) array_filter($facets['areaTree'], static fn ($a) => $a['value'] !== 'none');

// tag: prima quelli scelti, poi per numero di report; i tag senza report con i filtri attuali spariscono
$tags = array_values(array_filter($facets['tags'], static fn ($t) => $t['n'] > 0 || $filters->has('tag', $t['value'])));
foreach ($filters->get('tag') as $t) {
    if (!in_array($t, array_column($tags, 'value'), true)) {
        $tags[] = ['value' => $t, 'label' => $t, 'n' => 0];
    }
}
usort($tags, static fn ($a, $b) => [$filters->has('tag', $b['value']), $b['n'], $a['label']] <=> [$filters->has('tag', $a['value']), $a['n'], $b['label']]);

$ws = array_values(array_filter($facets['workspaces'], static fn ($w) => $w['n'] > 0 || $filters->has('ws', $w['value'])));
usort($ws, static fn ($a, $b) => [$filters->has('ws', $b['value'])] <=> [$filters->has('ws', $a['value'])]); // scelti in cima (ordinamento stabile)
$visible = 8; // voci mostrate prima di «Mostra tutti»: la colonna deve stare in uno schermo
$picked = static fn (string $key) => count($filters->get($key)) ? ' <span class="rg-facet-count">(' . count($filters->get($key)) . ')</span>' : '';
$kindsList = $facets['kinds'];
$appr = $facets['endorsements'];
?>
<details class="rg-side-wrap" open data-side>
    <summary class="rg-side-toggle"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Filtri<?= $active ? ' (' . count($active) . ')' : '' ?></summary>
    <form class="rg-side" method="get" action="<?= url($here) ?>" aria-label="Filtri">
        <?= $view->partial('partials/hidden_params', ['params' => $keep]) ?>
        <div class="rg-side-head">
            <span>Filtri</span>
            <button type="button" class="rg-side-hide" data-side-hide title="Nascondi i filtri per dare più spazio ai report">
                <i class="fa-solid fa-angles-left" aria-hidden="true"></i> Nascondi</button>
        </div>

        <details class="rg-facet" open>
            <summary>Categoria<?= $picked('area') ?></summary>
            <?php if ($hasCategories): ?>
                <?php foreach ($areas as $a): ?>
                    <label class="rg-check depth-<?= min(4, $a['depth']) ?>">
                        <input type="checkbox" name="area[]" value="<?= e($a['value']) ?>" <?= $checked('area', $a['value']) ?> data-autosubmit-check>
                        <i class="<?= e($a['icon'] ?: 'fa-solid fa-shapes') ?> rg-check-ico" aria-hidden="true"></i>
                        <span class="rg-check-label"><?= e($a['label']) ?></span><span class="rg-n"><?= (int) $a['n'] ?></span>
                    </label>
                <?php endforeach ?>
            <?php elseif ($user->isAdmin()): ?>
                <p class="rg-hint-small">Nessuna categoria assegnata. <a href="<?= url('admin/categorie') ?>">Assegna le categorie</a>.</p>
            <?php else: ?>
                <p class="rg-hint-small">Nessuna categoria, per ora.</p>
            <?php endif ?>
        </details>

        <?php if ($tags || $tagsExist): ?>
            <details class="rg-facet" open>
                <summary>Tag<?= $picked('tag') ?> <span class="rg-facet-note"><?= count($filters->get('tag')) > 1 ? 'tutti insieme' : '' ?></span></summary>
                <?php if (!$tags): ?>
                    <p class="rg-hint-small">Nessuno di questi report ha tag.</p>
                <?php endif ?>
                <?php if (count($tags) > 8): ?>
                    <input type="search" class="rg-facet-find" placeholder="Cerca un tag" aria-label="Cerca un tag" data-facet-find>
                <?php endif ?>
                <div class="rg-facet-list" data-facet-list>
                    <?php foreach ($tags as $i => $t): ?>
                        <label class="rg-check" data-facet-item <?= $i >= $visible ? 'data-extra hidden' : '' ?>>
                            <input type="checkbox" name="tag[]" value="<?= e($t['value']) ?>" <?= $checked('tag', $t['value']) ?> data-autosubmit-check>
                            <span class="rg-check-label">#<?= e($t['label']) ?></span><span class="rg-n"><?= (int) $t['n'] ?></span>
                        </label>
                    <?php endforeach ?>
                    <?php if (count($tags) > $visible): ?>
                        <button type="button" class="rg-more" data-facet-more data-less="Mostra meno">Mostra tutti (<?= count($tags) ?>)</button>
                    <?php endif ?>
                </div>
            </details>
        <?php endif ?>

        <details class="rg-facet" open>
            <summary>Dati aggiornati<?= $filters->fresh !== '' ? ' <span class="rg-facet-count">(1)</span>' : '' ?></summary>
            <label class="rg-check"><input type="radio" name="fresh" value="" <?= $filters->fresh === '' ? 'checked' : '' ?> data-autosubmit-check>
                <span class="rg-check-label">Qualsiasi data</span></label>
            <?php foreach (CatalogFilters::FRESH as $k => $l): ?>
                <label class="rg-check"><input type="radio" name="fresh" value="<?= e($k) ?>" <?= $filters->fresh === $k ? 'checked' : '' ?> data-autosubmit-check>
                    <span class="rg-check-label"><?= e($l) ?></span><span class="rg-n"><?= (int) ($facets['fresh'][$k] ?? 0) ?></span></label>
            <?php endforeach ?>
        </details>

        <details class="rg-facet">
            <summary>Workspace<?= $picked('ws') ?> <span class="rg-facet-note">cartelle di Power BI</span></summary>
            <?php if (count($ws) > 8): ?>
                <input type="search" class="rg-facet-find" placeholder="Cerca un workspace" aria-label="Cerca un workspace" data-facet-find>
            <?php endif ?>
            <div class="rg-facet-list" data-facet-list>
                <?php foreach ($ws as $i => $w): ?>
                    <label class="rg-check" data-facet-item <?= $i >= $visible ? 'data-extra hidden' : '' ?>>
                        <input type="checkbox" name="ws[]" value="<?= e($w['value']) ?>" <?= $checked('ws', $w['value']) ?> data-autosubmit-check>
                        <i class="fa-solid fa-cloud rg-check-ico rg-ico-ws" aria-hidden="true"></i>
                        <span class="rg-check-label"><?= e($w['label']) ?></span><span class="rg-n"><?= (int) $w['n'] ?></span>
                    </label>
                <?php endforeach ?>
                <?php if (count($ws) > $visible): ?>
                    <button type="button" class="rg-more" data-facet-more data-less="Mostra meno">Mostra tutti (<?= count($ws) ?>)</button>
                <?php endif ?>
            </div>
        </details>

        <?php if (count($kindsList) > 1 || $filters->get('kind')): ?>
            <details class="rg-facet">
                <summary>Tipo<?= $picked('kind') ?></summary>
                <?php foreach ($kindsList as $k): ?>
                    <label class="rg-check">
                        <input type="checkbox" name="kind[]" value="<?= e($k['value']) ?>" <?= $checked('kind', $k['value']) ?> data-autosubmit-check>
                        <span class="rg-check-label"><?= e($kinds[$k['value']] ?? $k['value']) ?></span><span class="rg-n"><?= (int) $k['n'] ?></span>
                    </label>
                <?php endforeach ?>
            </details>
        <?php endif ?>

        <?php if ($appr || $filters->get('appr')): ?>
            <details class="rg-facet">
                <summary>Approvazione<?= $picked('appr') ?></summary>
                <?php foreach ($appr as $en): ?>
                    <label class="rg-check">
                        <input type="checkbox" name="appr[]" value="<?= e($en['value']) ?>" <?= $checked('appr', $en['value']) ?> data-autosubmit-check>
                        <span class="rg-check-label"><?= $en['value'] === 'Certified' ? 'Certificati' : 'Promossi' ?></span><span class="rg-n"><?= (int) $en['n'] ?></span>
                    </label>
                <?php endforeach ?>
            </details>
        <?php endif ?>

        <noscript><button class="btn btn-sm btn-primary mt-2">Applica i filtri</button></noscript>
    </form>
</details>
