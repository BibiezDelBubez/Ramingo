<?php
/** @var string $content  @var string $title  @var Ramingo\Services\Auth\CurrentUser $user */
?><!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '') ?> · <?= e($appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/logo.svg') ?>">
    <link rel="preload" href="<?= asset('fonts/rubik-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('fontawesome/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<?php
// Catalogo e ricerca usano tutta la larghezza (filtri a sinistra, più colonne di report sugli schermi larghi);
// le pagine da leggere (scheda report, amministrazione) restano centrate. L'intestazione è sempre a tutta larghezza.
$box = !empty($homeSearch) ? 'rg-wide' : 'container-xl';
?>
<body data-base="<?= e(url()) ?>">
<header class="rg-header">
    <div class="rg-wide d-flex flex-wrap align-items-center gap-3 py-2">
        <a class="rg-brand" href="<?= url() ?>"><img src="<?= asset(!empty($logoIntro) ? 'img/logo_animato.svg' : 'img/logo.svg') ?>" alt="" width="34" height="33"> <?= e($appName) ?></a>
        <?php /* Sulla pagina iniziale questa ricerca resta nascosta finché quella grande è visibile (vedi app.js) */ ?>
        <form class="rg-search flex-grow-1<?= !empty($homeSearch) ? ' rg-search-docked' : '' ?>" action="<?= url('cerca') ?>" method="get" role="search" autocomplete="off" data-suggest>
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-control" aria-label="Cerca nei report"
                   placeholder="Cerca un report, con parole tue">
            <div class="rg-suggest" hidden></div>
        </form>
        <nav class="d-flex align-items-center gap-3 small">
            <a href="<?= url() ?>">Catalogo</a>
            <a href="<?= url('fonti') ?>">Fonti dei dati</a>
            <?php if ($aiNav): ?><a href="<?= url('ai/impostazioni') ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> La mia AI</a><?php endif ?>
            <?php if ($user->isAdmin()): ?><a href="<?= url('admin') ?>">Amministrazione</a><?php endif ?>
            <span class="text-secondary"><i class="fa-solid fa-user"></i> <?= e($user->name) ?></span>
            <?php if ($user->oid): ?>
                <form method="post" action="<?= url('logout') ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="btn btn-link btn-sm p-0">Esci</button>
                </form>
            <?php endif ?>
        </nav>
    </div>
</header>
<?php if (!empty($homeSearch)) echo $view->partial('partials/home_search', get_defined_vars()) ?>

<main class="<?= $box ?> py-4">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach ?>
    <?= $content ?>
</main>

<footer class="<?= $box ?> pb-4 small text-secondary">
    <?= $view->partial('partials/source_legend') ?>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ($scripts ?? [] as $script): ?><script src="<?= asset('js/' . $script) ?>"></script><?php endforeach ?>
</body>
</html>
