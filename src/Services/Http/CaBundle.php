<?php
declare(strict_types=1);

namespace Ramingo\Services\Http;

/**
 * Sceglie il file dei certificati per le chiamate HTTPS, senza percorsi da sistemare a mano.
 *
 * Ordine:
 *  1. http.ca_bundle in config.php, se il file esiste (serve solo con un proxy che ispeziona l'HTTPS
 *     e usa un certificato aziendale);
 *  2. curl.cainfo / openssl.cafile di php.ini, se il file esiste;
 *  3. i file di XAMPP accanto al PHP in uso (php\extras\ssl\cacert.pem, apache\bin\curl-ca-bundle.crt);
 *  4. resources/cacert.pem incluso in Ramingo (solo su Windows: altrove PHP usa i certificati di sistema).
 * Su Windows, in più, cURL consulta anche i certificati di Windows (dove l'IT installa quelli aziendali).
 */
final class CaBundle
{
    /** @return array{path:?string, source:string, warning:?string} */
    public static function resolve(?string $configured): array
    {
        $warning = null;
        $configured = trim((string) $configured);
        if ($configured !== '') {
            if (is_file($configured)) {
                return ['path' => $configured, 'source' => 'config.php (http.ca_bundle)', 'warning' => null];
            }
            $warning = "http.ca_bundle in config.php indica un file che su questa macchina non esiste ($configured): lo ignoro. Puoi lasciarlo vuoto.";
        }

        foreach (['curl.cainfo', 'openssl.cafile'] as $ini) {
            $v = trim((string) ini_get($ini), " \"'");
            if ($v !== '' && is_file($v)) {
                return ['path' => $v, 'source' => "php.ini ($ini)", 'warning' => $warning];
            }
        }

        foreach (self::phpDirs() as $dir) {
            foreach ([$dir . '/extras/ssl/cacert.pem', dirname($dir) . '/apache/bin/curl-ca-bundle.crt'] as $f) {
                if (is_file($f)) {
                    return ['path' => self::clean($f), 'source' => 'XAMPP', 'warning' => $warning];
                }
            }
        }

        $own = (defined('RAMINGO_ROOT') ? RAMINGO_ROOT : dirname(__DIR__, 3)) . '/resources/cacert.pem';
        if (DIRECTORY_SEPARATOR === '\\' && is_file($own)) {
            return ['path' => self::clean($own), 'source' => 'Ramingo (resources/cacert.pem)', 'warning' => $warning];
        }
        return ['path' => null, 'source' => 'certificati di sistema', 'warning' => $warning];
    }

    /** Cartelle in cui può trovarsi il PHP in uso (CLI o modulo di Apache). */
    private static function phpDirs(): array
    {
        $dirs = [];
        if (($ini = php_ini_loaded_file()) !== false) {
            $dirs[] = dirname($ini);
        }
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            $dirs[] = dirname(PHP_BINARY);
        }
        if (($ext = (string) ini_get('extension_dir')) !== '') {
            $dirs[] = dirname(rtrim($ext, '/\\'));
        }
        $dirs[] = PHP_BINDIR;
        return array_values(array_unique(array_map([self::class, 'clean'], $dirs)));
    }

    private static function clean(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
