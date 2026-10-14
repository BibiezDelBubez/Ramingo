<div class="d-flex flex-wrap gap-2 align-items-center">
    <span>Provenienza delle informazioni:</span>
    <?php foreach (Ramingo\Domain\Source::cases() as $src): ?>
        <?= $view->partial('partials/source_badge', ['source' => $src]) ?>
    <?php endforeach ?>
    <a href="<?= url('fonti') ?>">cosa significano</a>
</div>
