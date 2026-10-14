<?php
/**
 * Opzioni <option> dell'albero categorie, con rientro. Riusato da tutti i select.
 * @var array $flat  @var ?int $selected  @var ?int $exclude (la categoria e i suoi figli non selezionabili)
 */
$selected ??= null;
$excludeIds ??= [];
foreach ($flat as $a):
    if (in_array((int) $a['id'], $excludeIds, true)) continue; ?>
    <option value="<?= (int) $a['id'] ?>" <?= $selected !== null && (int) $selected === (int) $a['id'] ? 'selected' : '' ?>><?= str_repeat('— ', (int) $a['depth']) . e($a['name']) ?></option>
<?php endforeach;
