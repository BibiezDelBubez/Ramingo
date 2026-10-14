<?php
/**
 * Badge di provenienza: UNICO punto in cui si disegna la fonte di un dato.
 * @var Ramingo\Domain\Source $source  @var ?Ramingo\Domain\Sourced $sourced (facoltativo, per autore/data)
 */
$sourced ??= null;
$tip = $source->explanation();
if ($sourced?->author) {
    $tip .= ' Autore: ' . $sourced->author . '.';
}
if ($sourced?->at) {
    $tip .= ' Data: ' . fmt_date($sourced->at, true) . '.';
}
if ($sourced?->generator) {
    $tip .= ' Generatore: ' . $sourced->generator . '.';
}
?><span class="src-badge <?= e($source->cssClass()) ?>" title="<?= e($tip) ?>"><i class="<?= e($source->icon()) ?>"></i> <?= e($source->label()) ?></span>
