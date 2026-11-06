<?php
declare(strict_types=1);

namespace Ramingo\Services;

use Ramingo\Core\App;

/**
 * Controllo dell'installazione: dice in una riga cosa non va e come sistemarlo.
 * Lo usano la pagina Amministrazione → Collegamento Power BI (PHP di Apache)
 * e bin/check.php (PHP da riga di comando, quello della sincronizzazione).
 */
final class Doctor
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    public function __construct(private readonly App $app)
    {
    }

    /** @return array<int, array{status:string, label:string, message:string}> */
    public function run(): array
    {
        $out = [];
        $add = static function (string $status, string $label, string $message) use (&$out): void {
            $out[] = ['status' => $status, 'label' => $label, 'message' => $message];
        };

        // PHP ed estensioni
        $add(PHP_VERSION_ID >= 80100 ? self::OK : self::FAIL, 'Versione di PHP',
            PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '' : ': serve PHP 8.1 o più recente.'));
        $required = ['pdo_sqlite' => 'database', 'curl' => 'collegamento a Power BI', 'openssl' => 'cifratura e HTTPS', 'mbstring' => 'testi'];
        foreach ($required as $ext => $why) {
            $ok = extension_loaded($ext);
            $add($ok ? self::OK : self::FAIL, "Estensione $ext", $ok ? 'attiva' : "non attiva (serve per: $why). In php.ini togli il \";\" davanti a extension=$ext e riavvia Apache.");
        }
        $zip = class_exists(\ZipArchive::class);
        $add($zip ? self::OK : self::WARN, 'Estensione zip', $zip ? 'attiva' : 'non attiva: Ramingo non legge quali campi usa ogni report. In php.ini attiva extension=zip.');

        // Cartelle scrivibili
        foreach (['storage', 'storage/logs'] as $dir) {
            $path = $this->app->path($dir);
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $add($ok ? self::OK : self::FAIL, "Cartella $dir", $ok ? 'scrivibile' : "non esiste o non è scrivibile: $path");
        }

        // Database
        try {
            $db = $this->app->db();
            preg_match_all('~CREATE TABLE IF NOT EXISTS (\w+)~', (string) file_get_contents($this->app->path('database/schema.sql')), $m);
            $have = $db->column("SELECT name FROM sqlite_master WHERE type = 'table'");
            $missing = array_diff($m[1], $have);
            $add($missing ? self::FAIL : self::OK, 'Database',
                $missing ? 'da aggiornare (mancano: ' . implode(', ', array_slice($missing, 0, 5)) . '). Esegui: php bin\migrate.php' : 'aggiornato');
        } catch (\Throwable $e) {
            $add(self::FAIL, 'Database', 'non si apre: ' . $e->getMessage());
        }

        // Cifratura
        $box = $this->app->secretBox();
        $p = $box->problem();
        $add($p === null ? self::OK : self::FAIL, 'Cifratura dei segreti', $p ?? 'pronta, chiave da ' . $box->keySource());

        // Segreto Power BI
        try {
            $s = $this->app->powerBiSettings();
            if (($err = $s->secretError()) !== null) {
                $add(self::FAIL, 'Segreto Power BI', 'salvato ma illeggibile: ' . $err);
            } elseif ($s->isComplete()) {
                $add(self::OK, 'Credenziali Power BI', 'complete');
            } else {
                $add(self::WARN, 'Credenziali Power BI', 'da compilare (tenant ID, client ID e segreto).');
            }
        } catch (\Throwable $e) {
            $add(self::FAIL, 'Credenziali Power BI', 'non leggibili: ' . $e->getMessage());
        }

        // Certificati HTTPS
        $ca = $this->app->http()->caBundle();
        $add($ca['warning'] ? self::WARN : self::OK, 'Certificati HTTPS',
            ($ca['path'] !== null ? $ca['path'] . ' (' . $ca['source'] . ')' : $ca['source']) . ($ca['warning'] ? '. Nota: ' . $ca['warning'] : ''));

        // PHP per la sincronizzazione in background
        try {
            $php = $this->app->backgroundSync()->phpBinary();
            $cfg = (string) $this->app->config()->get('app.php_cli', '');
            $warn = $cfg !== '' && !is_file($cfg);
            $add($warn ? self::WARN : self::OK, 'PHP per la sincronizzazione',
                $php . ($warn ? ". Nota: app.php_cli in config.php indica un file che qui non esiste ($cfg): lo ignoro." : ''));
        } catch (\Throwable $e) {
            $add(self::FAIL, 'PHP per la sincronizzazione', $e->getMessage());
        }

        return $out;
    }

    /** Solo le righe con problemi. */
    public static function problems(array $checks): array
    {
        return array_values(array_filter($checks, static fn ($c) => $c['status'] !== self::OK));
    }
}
