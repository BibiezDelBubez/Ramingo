<?php
/**
 * @var array $tree  @var array $flat  @var array $paths  @var array $counts  @var array $icons
 * @var array $workspaces  @var array $items [workspace_id => [...]]  @var int $unclassifiedTotal
 */
$kinds = Ramingo\Models\CatalogRepository::KINDS;
?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Categorie</nav>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Categorie</h1>
        <p class="text-secondary mb-0 rg-measure">
            I <strong>workspace</strong> sono le cartelle di Power BI: dicono <em>dove</em> è salvato un report.
            Le <strong>categorie</strong> esistono solo in Ramingo e dicono <em>di cosa parla</em>: sono le voci
            con cui i colleghi sfogliano il catalogo. Qui decidi in quale categoria compare ogni report;
            in Power BI non cambia niente.
        </p>
    </div>
</div>
<div class="rg-howto mb-3">
    <div><strong>1.</strong> A sinistra crea le categorie, per argomento (es. «Operations e logistica», «Amministrazione e finanza», «Commerciale»).</div>
    <div><strong>2.</strong> A destra scegli la categoria dei report. Scorciatoia: una categoria per tutto il workspace, che vale per tutti i suoi report.</div>
    <div><strong>3.</strong> Se un report parla d'altro, scegli per lui una categoria diversa.</div>
</div>

<input type="hidden" id="rg-csrf" value="<?= e($csrf) ?>">
<div class="rg-toast" id="rg-toast" hidden></div>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="rg-section">
            <h2 class="h5 mb-1"><i class="fa-solid fa-shapes rg-ico-cat"></i> Categorie di Ramingo</h2>
            <p class="small text-secondary">Per cambiare l'ordine usa le frecce, oppure trascina una categoria sul bordo alto o basso di un'altra (compare una linea). Lasciala al centro di un'altra per metterla dentro. Doppio clic per rinominarla.</p>
            <form method="post" action="<?= url('admin/categorie/ordine') ?>" class="rg-cat-order mb-3">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label for="rg-cat-order">Nel catalogo mostra le categorie</label>
                <select id="rg-cat-order" name="order" class="form-select form-select-sm" data-autosubmit>
                    <option value="list" <?= empty($alphabetical) ? 'selected' : '' ?>>nell'ordine di questo elenco</option>
                    <option value="alpha" <?= !empty($alphabetical) ? 'selected' : '' ?>>in ordine alfabetico</option>
                </select>
                <noscript><button class="btn btn-sm btn-outline-secondary">Salva</button></noscript>
                <?php if (!empty($alphabetical)): ?>
                    <p class="small text-secondary mb-0 mt-1">L'ordine qui sotto resta salvato, ma il catalogo ora lo ignora.</p>
                <?php endif ?>
            </form>
            <form method="post" action="<?= url('admin/categorie/crea') ?>" class="row g-1 mb-3">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="col-12 col-sm-5"><input class="form-control form-control-sm" name="name" placeholder="Nuova categoria" required maxlength="80"></div>
                <div class="col-7 col-sm-4">
                    <select class="form-select form-select-sm" name="parent_id" aria-label="Dentro a">
                        <option value="">primo livello</option>
                        <?= $view->partial('partials/area_options', ['flat' => $flat]) ?>
                    </select>
                </div>
                <div class="col-5 col-sm-3"><button class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-plus"></i> Crea</button></div>
            </form>

            <div class="rg-dropzone" data-drop-root><i class="fa-solid fa-arrow-up"></i> Primo livello</div>
            <?php if (!$tree): ?><p class="small text-secondary mt-2">Nessuna categoria: creane una qui sopra.</p><?php endif ?>
            <ul class="rg-tree rg-tree-root">
                <?php foreach ($tree as $node) echo $view->partial('partials/category_node', compact('node', 'flat', 'counts', 'icons', 'csrf')) ?>
            </ul>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="rg-section">
            <h2 class="h5 mb-1"><i class="fa-solid fa-chart-column rg-ico-cat"></i> Assegna i Report alle categorie</h2>
            <p class="small text-secondary mb-2">
                I report sono qui raggruppati per Workspace di Power BI. Cioè come apparirebbero sul sito di Power BI.
            </p>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <span class="small"><?= $unclassifiedTotal ? '<strong>' . (int) $unclassifiedTotal . '</strong> report ancora da classificare' : 'Tutti i report hanno una categoria.' ?></span>
                <input type="search" class="form-control form-control-sm rg-filter-input" placeholder="Cerca un report o un workspace" data-filter-list="#rg-ws-list" aria-label="Cerca">
            </div>
            <div id="rg-ws-list">
            <?php foreach ($workspaces as $w):
                $wsItems = $items[$w['id']] ?? [];
                if (!$wsItems) { continue; }
                $wsArea  = $w['area_id'] ? (int) $w['area_id'] : null;
                $todo    = count(array_filter($wsItems, static fn ($it) => $it['own_area_id'] === null && $wsArea === null)); ?>
                <details class="rg-group-ws" data-filter-text="<?= e(mb_strtolower($w['name'] . ' ' . implode(' ', array_column($wsItems, 'name')))) ?>" data-filter-ws="<?= e(mb_strtolower($w['name'])) ?>" <?= $todo ? 'open' : '' ?>>
                    <summary>
                        <span class="rg-group-ws-name"><i class="fa-solid fa-cloud rg-ico-ws" aria-hidden="true"></i><?= e($w['name']) ?></span>
                        <span class="small text-secondary">workspace Power BI · <?= count($wsItems) ?> report</span>
                        <?php if ($todo): ?><span class="rg-chip rg-chip-empty"><?= $todo ?> da classificare</span><?php endif ?>
                    </summary>
                    <div class="rg-group-all">
                        <span>Categoria per tutto il workspace (vale anche per i report pubblicati in futuro):</span>
                        <?= $view->partial('partials/assign_select', ['kind' => 'workspace', 'id' => $w['id'], 'current' => $wsArea, 'flat' => $flat, 'csrf' => $csrf]) ?>
                    </div>
                    <ul class="rg-group-items">
                    <?php foreach ($wsItems as $it): $own = $it['own_area_id'] !== null ? (int) $it['own_area_id'] : null; ?>
                        <li data-filter-name="<?= e(mb_strtolower($it['name'])) ?>">
                            <span class="flex-grow-1"><?= e($it['name']) ?> <span class="text-secondary small">· <?= e($kinds[$it['kind']] ?? $it['kind']) ?></span></span>
                            <?= $view->partial('partials/assign_select', ['kind' => $it['entity_type'], 'id' => $it['id'], 'current' => $own, 'flat' => $flat, 'csrf' => $csrf,
                                'inherit' => $wsArea !== null ? ($paths[$wsArea] ?? null) : null]) ?>
                        </li>
                    <?php endforeach ?>
                    </ul>
                </details>
            <?php endforeach ?>
            </div>
        </section>
    </div>
</div>
