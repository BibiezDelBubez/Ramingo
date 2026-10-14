<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/**
 * Cifratura simmetrica delle chiavi personali (libsodium, XSalsa20-Poly1305).
 * La chiave di cifratura è security.app_key in config/config.php, fuori dalla webroot
 * e fuori dal database: chi copia solo il database non può leggere le chiavi.
 */
final class SecretBox
{
    private ?string $key = null;

    public function __construct(string $hexKey)
    {
        if (preg_match('~^[0-9a-f]{64}$~i', $hexKey)) {
            $this->key = hex2bin($hexKey);
        }
    }

    /** Pronto all'uso? Servono estensione sodium attiva e security.app_key valida. */
    public function available(): bool
    {
        return $this->key !== null && function_exists('sodium_crypto_secretbox');
    }

    public function encrypt(string $plain): string
    {
        $this->assertAvailable();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encoded): string
    {
        $this->assertAvailable();
        $raw = base64_decode($encoded, true);
        $nonce = substr((string) $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr((string) $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new AiException('Impossibile decifrare la chiave salvata (security.app_key cambiata?). Inseriscila di nuovo.');
        }
        return $plain;
    }

    private function assertAvailable(): void
    {
        if (!$this->available()) {
            throw new AiException('Salvataggio cifrato non disponibile: attiva extension=sodium in php.ini e genera security.app_key (php bin\generate-key.php).');
        }
    }
}
