<?php
/** Data dei dati di un report. @var array $f risultato di Freshness::of()  @var bool $showUnknown */
$showUnknown ??= false;
$icons = ['fresh' => 'fa-regular fa-clock', 'stale' => 'fa-solid fa-hourglass-end', 'failed' => 'fa-solid fa-triangle-exclamation', 'unknown' => 'fa-regular fa-circle-question'];
if ($f['label'] === null && !$showUnknown) {
    return;
}
?><span class="rg-fresh fresh-<?= e($f['state']) ?>" title="<?= e($f['title']) ?>"><i class="<?= e($icons[$f['state']]) ?>" aria-hidden="true"></i><?= e($f['label'] ?? 'Data dei dati non disponibile') ?></span>
