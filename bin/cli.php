<?php
/**
 * Bootstrap comune per gli script da riga di comando.
 * Impedisce l'esecuzione via browser anche se la cartella bin/ finisse esposta.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var Ramingo\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

/** Legge --chiave=valore dagli argomenti. */
function cli_option(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--$name=")) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return $default;
}

return $app;
