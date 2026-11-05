<?php /** File HTML autonomo della guida AI (scaricato da /ai/guida). @var string $appName */ ?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>L'AI in <?= e($appName) ?> · guida</title>
<style>
    body { font-family: Rubik, "Segoe UI", system-ui, sans-serif; color: #1a1c3a; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.55; }
    h1 { color: #2f3196; margin-bottom: .2rem; }
    .sub { color: #5d6180; margin-top: 0; }
    h2 { color: #2f3196; font-size: 1.15rem; margin-top: 1.8rem; border-bottom: 3px solid #ffd13a; padding-bottom: .2rem; }
    li { margin-bottom: .45rem; }
    code { background: #f4f5fa; padding: 0 .3rem; border-radius: .25rem; }
    @media print { body { margin: 0; } }
</style>
</head>
<body>
<h1>L'AI in <?= e($appName) ?></h1>
<p class="sub">Cosa può fare, cosa non può fare e di cosa ha bisogno · aggiornata al <?= e(date('d/m/Y')) ?></p>
<?= $view->partial('partials/ai_guide', get_defined_vars()) ?>
</body>
</html>
