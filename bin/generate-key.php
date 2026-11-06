<?php
/**
 * Genera security.app_key (chiave di cifratura dei segreti salvati nel database).
 * Uso: php bin\generate-key.php   e copiare il valore in config/config.php.
 * Facoltativo: se security.app_key è vuota, Ramingo crea da solo config/app.key.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
echo bin2hex(random_bytes(32)), "\n";
