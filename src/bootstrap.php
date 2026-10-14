<?php
/**
 * Bootstrap comune a web (public/index.php) e CLI (bin/*.php).
 * Unico punto in cui si configurano autoload, errori, timezone e configurazione.
 */
declare(strict_types=1);

define('RAMINGO_ROOT', dirname(__DIR__));

// Autoload PSR-4 minimale: Ramingo\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ramingo\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = RAMINGO_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require RAMINGO_ROOT . '/src/helpers.php';

$configFile = RAMINGO_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = RAMINGO_ROOT . '/config/config.example.php';
}

$app = new Ramingo\Core\App(Ramingo\Core\Config::fromFile($configFile));

date_default_timezone_set($app->config()->get('app.timezone', 'Europe/Rome'));

$debug = (bool) $app->config()->get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RAMINGO_ROOT . '/storage/logs/php-errors.log');

return $app;
