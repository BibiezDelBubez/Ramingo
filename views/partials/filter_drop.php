<?php
/**
 * Un filtro a tendina della barra filtri (senza JavaScript: <details>).
 * @var string $key  parametro URL         @var string $label  nome del filtro
 * @var string $icon icona                 @var array  $options [{value, label, n}]
 * @var string $current valore attivo      @var callable $link fn(array $change): string
 * @var bool   $searchable mostra la casella per filtrare le voci
 */
$searchable ??= count($options) > 8;
$currentLabel = null;
foreach ($options as $o) {
    if ((string) $o['value'] === $current) {
        $currentLabel = $o['label'];
    }
}
?>
<details class="rg-drop" data-drop>
    <summary class="rg-drop-btn<?= $current !== '' ? ' is-set' : '' ?>">
        <i class="<?= e($icon) ?>" aria-hidden="true"></i>
        <span><?= e($label) ?><?php if ($currentLabel !== null): ?>: <strong><?= e($currentLabel) ?></strong><?php endif ?></span>
        <i class="fa-solid fa-chevron-down rg-drop-caret" aria-hidden="true"></i>
    </summary>
    <div class="rg-drop-panel">
        <?php if ($searchable): ?>
            <input type="search" class="rg-drop-find" placeholder="Filtra l'elenco" aria-label="Filtra l'elenco" data-drop-find>
        <?php endif ?>
        <a class="rg-opt<?= $current === '' ? ' active' : '' ?>" href="<?= e($link([$key => null])) ?>">Tutti</a>
        <?php foreach ($options as $o): ?>
            <a class="rg-opt<?= (string) $o['value'] === $current ? ' active' : '' ?>" href="<?= e($link([$key => $o['value']])) ?>" data-drop-item>
                <span><?= e($o['label']) ?></span><span class="rg-n"><?= (int) $o['n'] ?></span></a>
        <?php endforeach ?>
    </div>
</details>
