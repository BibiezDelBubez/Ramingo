<?php /** Tag di un elemento; quelli scritti a mano portano al catalogo filtrato. @var array $tags [{tag, source}] */ ?>
<?php foreach ($tags as $t): ?>
    <?php if ($t['source'] === 'manual'): ?>
        <a class="rg-tag tag-manual" href="<?= e(url('', ['tag' => $t['tag']])) ?>" title="Tutti i report con questo tag">#<?= e($t['tag']) ?></a>
    <?php else: ?>
        <span class="rg-tag tag-<?= e($t['source']) ?>" title="<?= e(Ramingo\Domain\Source::from($t['source'])->label()) ?>">#<?= e($t['tag']) ?></span>
    <?php endif ?>
<?php endforeach;
