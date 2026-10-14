<?php
/**
 * Genera security.app_key (chiave di cifratura per le chiavi AI personali).
 * Uso: php bin\generate-key.php   e copiare il valore in config/config.php.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
echo bin2hex(random_bytes(32)), "\n";
if (!function_exists('sodium_crypto_secretbox')) {
    fwrite(STDERR, "Attenzione: l'estensione sodium non è attiva. In php.ini togli il ';' davanti a extension=sodium e riavvia Apache.\n");
}
