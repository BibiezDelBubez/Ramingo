<?php
/** Un nodo dell'albero categorie (ricorsivo). @var array $node  @var array $flat  @var array $counts  @var array $icons */
$id = (int) $node['id'];
// La categoria stessa e le sue discendenti non possono diventare il suo genitore
$collect = static function (array $n) use (&$collect): array {
    $ids = [(int) $n['id']];
    foreach ($n['children'] as $c) {
        $ids = array_merge($ids, $collect($c));
    }
    return $ids;
};
$excluded = $collect($node);
?>
<li class="rg-node" draggable="true" data-drag-kind="area" data-drag-id="<?= $id ?>" data-drop-area="<?= $id ?>">
    <div class="rg-node-row">
        <i class="fa-solid fa-grip-vertical rg-grip" title="Trascina"></i>
        <i class="<?= e($node['icon'] ?: 'fa-solid fa-shapes') ?>"></i>
        <span class="rg-node-name" data-rename="<?= $id ?>" title="Doppio clic per rinominare"><?= e($node['name']) ?></span>
        <span class="rg-count"><?= (int) ($counts[$id] ?? 0) ?></span>
        <details class="rg-node-edit">
            <summary title="Modifica"><i class="fa-solid fa-ellipsis"></i></summary>
            <div class="rg-node-panel">
                <form method="post" action="<?= url('admin/categorie/modifica') ?>" class="d-flex gap-1 mb-2">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
                    <input class="form-control form-control-sm" name="name" value="<?= e($node['name']) ?>" maxlength="80" aria-label="Nome">
                    <select class="form-select form-select-sm" name="icon" aria-label="Icona">
                        <?php foreach ($icons as $ic): ?><option value="<?= e($ic) ?>" <?= $ic === $node['icon'] ? 'selected' : '' ?>><?= e(str_replace(['fa-solid fa-', '-'], ['', ' '], $ic)) ?></option><?php endforeach ?>
                    </select>
                    <button class="btn btn-sm btn-primary">Salva</button>
                </form>
                <form method="post" action="<?= url('admin/categorie/sposta') ?>" class="d-flex gap-1 mb-2">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
                    <select class="form-select form-select-sm" name="parent_id" aria-label="Sposta dentro">
                        <option value="">— primo livello —</option>
                        <?= $view->partial('partials/area_options', ['flat' => $flat, 'selected' => $node['parent_id'] !== null ? (int) $node['parent_id'] : null, 'excludeIds' => $excluded]) ?>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">Sposta</button>
                </form>
                <form method="post" action="<?= url('admin/categorie/elimina') ?>" class="d-flex gap-1">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= $id ?>">
                    <select class="form-select form-select-sm" name="move_to" aria-label="Sposta il contenuto in">
                        <option value=""><?= $node['parent_id'] !== null ? 'contenuto alla categoria superiore' : 'contenuto da classificare' ?></option>
                        <?= $view->partial('partials/area_options', ['flat' => $flat, 'excludeIds' => $excluded]) ?>
                    </select>
                    <button class="btn btn-sm btn-outline-danger">Elimina</button>
                </form>
            </div>
        </details>
    </div>
    <?php if ($node['children']): ?>
        <ul class="rg-tree"><?php foreach ($node['children'] as $child) echo $view->partial('partials/category_node', ['node' => $child, 'flat' => $flat, 'counts' => $counts, 'icons' => $icons, 'csrf' => $csrf]) ?></ul>
    <?php endif ?>
</li>
