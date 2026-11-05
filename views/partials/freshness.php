<?php
/**
 * Data dei dati di un report. @var array $f risultato di Freshness::of()  @var bool $showUnknown
 * La data è sempre neutra; se l'ultimo aggiornamento non è riuscito compare a fianco un triangolo,
 * e passandoci sopra (o con il tasto Tab) si legge il problema.
 */
$showUnknown ??= false;
if ($f['label'] === null && !$showUnknown) {
    return;
}
$failed = $f['state'] === 'failed';
$state  = $failed ? 'fresh' : $f['state'];
$icons  = ['fresh' => 'fa-regular fa-clock', 'stale' => 'fa-solid fa-hourglass-end', 'unknown' => 'fa-regular fa-circle-question'];
?><span class="rg-fresh fresh-<?= e($state) ?>"<?= $failed ? '' : ' title="' . e($f['title']) . '"' ?>><i class="<?= e($icons[$state]) ?>" aria-hidden="true"></i><?= e($f['label'] ?? 'Data dei dati non disponibile') ?></span><?php if ($failed): ?><span class="rg-fresh-warn" tabindex="0" role="img" title="<?= e($f['title']) ?>" aria-label="<?= e($f['title']) ?>"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span><?php endif ?>
