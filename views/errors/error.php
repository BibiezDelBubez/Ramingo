<div class="rg-hero rg-error">
    <img class="rg-logo-fun" src="<?= asset('img/logo_animato.svg') ?>" alt="" width="72" height="69">
    <h1 class="h3"><?= (int) $status ?></h1>
    <p><?= e($message) ?></p>
    <a href="<?= url() ?>">Torna al catalogo</a>
</div>
