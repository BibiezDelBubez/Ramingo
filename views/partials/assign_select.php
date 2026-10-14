<?php
/**
 * Menu per scegliere la categoria di un workspace o di un singolo report/dashboard.
 * Cambia SOLO la categoria in Ramingo: Power BI non viene toccato.
 * @var string $kind workspace|report|dashboard  @var string $id  @var ?int $current  @var array $flat
 * @var ?string $label etichetta visibile (null = solo per lettori di schermo)
 * @var ?string $inherit per i report: categoria presa dal workspace, mostrata nella prima voce
 */
$label   ??= null;
$inherit ??= null;
$fieldId = 'as-' . $kind . '-' . $id;
$first = $kind === 'workspace'
    ? 'nessuna (da classificare)'
    : ($inherit !== null ? 'come il workspace: ' . $inherit : 'come il workspace');
?>
<form method="post" action="<?= url('admin/categorie/assegna') ?>" class="rg-assign">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="id" value="<?= e($id) ?>">
    <label class="<?= $label === null ? 'visually-hidden' : 'rg-assign-label' ?>" for="<?= e($fieldId) ?>"><?= e($label ?? 'Categoria') ?></label>
    <select name="area_id" id="<?= e($fieldId) ?>" class="form-select form-select-sm<?= $current === null && ($kind === 'workspace' || $inherit === null) ? ' is-empty' : '' ?>" data-autosubmit>
        <option value=""><?= e($first) ?></option>
        <?= $view->partial('partials/area_options', ['flat' => $flat, 'selected' => $current]) ?>
    </select>
    <noscript><button class="btn btn-sm btn-outline-secondary">Salva</button></noscript>
</form>
