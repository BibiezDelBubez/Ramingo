<?php
/**
 * Un valore con la sua provenienza. Testo multilinea mantenuto (nl2br dopo escape).
 * @var ?Ramingo\Domain\Sourced $s  @var string $empty testo se vuoto
 */
$empty ??= '—';
if ($s === null || $s->isEmpty()): ?>
    <span class="text-secondary"><?= e($empty) ?></span>
<?php else: ?>
    <span class="sourced"><?= nl2br(e($s->value)) ?> <?= $view->partial('partials/source_badge', ['source' => $s->source, 'sourced' => $s]) ?></span>
<?php endif;
