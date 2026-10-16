<?php /** @var string $message  @var string $line */ ?>
<section class="rg-lost">
    <img class="rg-lost-sign" src="<?= asset('img/domanda_404.svg') ?>" alt="" width="180" height="370">
    <div class="rg-lost-text">
        <p class="rg-lost-line"><?= e($line) ?></p>
        <h1 class="rg-lost-title">Errore 404 · pagina non trovata</h1>
        <p><?= e($message) ?></p>
        <form class="rg-lost-search" action="<?= url('cerca') ?>" method="get" role="search">
            <input type="search" name="q" class="form-control" placeholder="Cerca il report che ti serve" aria-label="Cerca nei report">
            <button class="rg-home-go">Cerca</button>
        </form>
        <a class="rg-lost-home" href="<?= url() ?>">Oppure torna al catalogo report</a>
    </div>
</section>
