<?php
declare(strict_types=1);

namespace Ramingo\Services;

/**
 * Avvia bin/sync.php in un processo separato, così la pagina web non resta in attesa.
 * Il lock dentro sync.php impedisce due sincronizzazioni contemporanee.
 */
final class BackgroundSync
{
    public function __construct(private readonly ?string $phpCli)
    {
    }

    public function start(string $mode): void
    {
        $php    = $this->phpBinary();
        $script = RAMINGO_ROOT . '/bin/sync.php';
        $mode   = $mode === 'full' ? 'full' : 'incremental';

        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = sprintf('start "" /B "%s" "%s" --mode=%s', $php, str_replace('/', '\\', $script), $mode);
            pclose(popen($cmd, 'r'));
        } else {
            exec(sprintf('%s %s --mode=%s > /dev/null 2>&1 &', escapeshellarg($php), escapeshellarg($script), $mode));
        }
    }

    /** php.exe: da config (app.php_cli) oppure accanto al php.ini in uso (XAMPP). */
    private function phpBinary(): string
    {
        if ($this->phpCli) {
            return $this->phpCli;
        }
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }
        $ini = php_ini_loaded_file();
        foreach ([$ini ? dirname($ini) : null, PHP_BINDIR] as $dir) {
            foreach (['php.exe', 'php'] as $name) {
                if ($dir && is_file($dir . DIRECTORY_SEPARATOR . $name)) {
                    return $dir . DIRECTORY_SEPARATOR . $name;
                }
            }
        }
        throw new \RuntimeException('php.exe non trovato: indicalo in config.php (app.php_cli).');
    }
}
