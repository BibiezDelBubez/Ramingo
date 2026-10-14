<?php
/** Riga "etichetta: valore" per dati Power BI, con il badge una sola volta in testa. @var array $facts [label => value] */
use Ramingo\Domain\Source;
?>
<div class="rg-facts">
    <div class="rg-facts-head"><?= $view->partial('partials/source_badge', ['source' => Source::PowerBi]) ?></div>
    <dl class="rg-dl">
        <?php foreach ($facts as $label => $value): if ($value === null || $value === '' || $value === []) continue; ?>
            <dt><?= e($label) ?></dt><dd><?= is_array($value) ? implode('<br>', array_map('e', $value)) : e($value) ?></dd>
        <?php endforeach ?>
    </dl>
</div>
