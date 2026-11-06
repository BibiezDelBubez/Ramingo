<?php
/**
 * Controlla l'installazione e dice cosa sistemare.  Uso:  php bin\check.php
 * Controlla il PHP da riga di comando (quello della sincronizzazione). Quello di Apache
 * si controlla da Amministrazione → Collegamento Power BI.
 */
declare(strict_types=1);

$app = require __DIR__ . '/cli.php';

$checks = (new Ramingo\Services\Doctor($app))->run();
$icon = ['ok' => '[OK]  ', 'warn' => '[!]   ', 'fail' => '[NO]  '];
foreach ($checks as $c) {
    echo $icon[$c['status']], $c['label'], ': ', $c['message'], "\n";
}
$fail = count(array_filter($checks, static fn ($c) => $c['status'] === 'fail'));
echo "\n", $fail ? "$fail problemi da sistemare.\n" : "Tutto a posto.\n";
exit($fail ? 1 : 0);
