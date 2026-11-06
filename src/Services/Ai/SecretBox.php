<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/**
 * Cifratura simmetrica dei segreti salvati nel database (segreto Power BI, chiavi AI personali).
 *
 * Formato attuale ("v2:"): AES-256-GCM con l'estensione openssl, attiva di serie in XAMPP e in
 * quasi ogni installazione PHP. Non serve modificare php.ini.
 * Formato vecchio (senza prefisso): libsodium. Si legge ancora se sodium c'è; chi lo legge lo
 * riscrive nel formato nuovo (vedi needsUpgrade()).
 *
 * La chiave è security.app_key in config/config.php; se è vuota, Ramingo ne crea una da solo in
 * config/app.key. In entrambi i casi sta fuori dalla webroot e fuori dal database: chi copia solo
 * il database non può leggere i segreti. Chi sposta Ramingo su un'altra macchina copia la cartella
 * config/ insieme al database.
 */
final class SecretBox
{
    private const PREFIX = 'v2:';
    private const CIPHER = 'aes-256-gcm';

    private ?string $key = null;
    private string $keyProblem = '';
    private string $keySource = 'nessuna';

    public function __construct(string $hexKey, ?string $keyFile = null)
    {
        $hexKey = trim($hexKey);
        if (preg_match('~^[0-9a-f]{64}$~i', $hexKey)) {
            $this->key = hex2bin($hexKey);
            $this->keySource = 'config/config.php (security.app_key)';
        } elseif ($hexKey !== '') {
            $this->keyProblem = 'security.app_key in config/config.php non è valida: deve essere di 64 caratteri 0-9 a-f (generala con php bin\generate-key.php) oppure vuota.';
        } elseif ($keyFile !== null) {
            $this->key = self::loadOrCreateKeyFile($keyFile, $this->keyProblem);
            if ($this->key !== null) {
                $this->keySource = 'config/app.key (creata da Ramingo)';
            }
        } else {
            $this->keyProblem = 'Manca la chiave di cifratura (security.app_key).';
        }
    }

    /** Si può cifrare e decifrare? */
    public function available(): bool
    {
        return $this->problem() === null;
    }

    /** Perché non si può cifrare, in italiano; null se è tutto a posto. */
    public function problem(): ?string
    {
        if ($this->key === null) {
            return $this->keyProblem ?: 'Manca la chiave di cifratura (security.app_key).';
        }
        if (!self::opensslReady() && !self::sodiumReady()) {
            return 'In questo PHP non sono attive né l\'estensione openssl né sodium: in php.ini togli il ";" davanti a extension=openssl e riavvia Apache.';
        }
        return null;
    }

    /** Da dove viene la chiave, per la pagina di controllo. */
    public function keySource(): string
    {
        return $this->keySource;
    }

    public function encrypt(string $plain): string
    {
        $this->assertAvailable();
        if (self::opensslReady()) {
            $iv = random_bytes(12);
            $tag = '';
            $ct = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($ct === false) {
                throw new AiException('Cifratura non riuscita (openssl).');
            }
            return self::PREFIX . base64_encode($iv . $tag . $ct);
        }
        // Ripiego: openssl assente ma sodium sì (raro).
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encoded): string
    {
        if ($this->key === null) {
            throw new AiException($this->problem() . ' Senza la chiave usata per salvarlo, il segreto va reinserito.');
        }
        if (str_starts_with($encoded, self::PREFIX)) {
            if (!self::opensslReady()) {
                throw new AiException('Per leggere il segreto serve l\'estensione openssl: in php.ini togli il ";" davanti a extension=openssl e riavvia Apache.');
            }
            $raw = base64_decode(substr($encoded, strlen(self::PREFIX)), true);
            $plain = is_string($raw) && strlen($raw) > 28
                ? openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16))
                : false;
        } else {
            if (!self::sodiumReady()) {
                throw new AiException('Il segreto è salvato nel formato vecchio, che si legge solo con l\'estensione sodium (qui non attiva). Reinseriscilo nel pannello e premi Salva: verrà salvato nel formato nuovo, che funziona ovunque.');
            }
            $raw = base64_decode($encoded, true);
            $nonce = substr((string) $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = strlen($nonce) === SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
                ? sodium_crypto_secretbox_open(substr((string) $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key)
                : false;
        }
        if ($plain === false) {
            throw new AiException('Il segreto è stato salvato con un\'altra chiave di cifratura (security.app_key diversa da quella della macchina dove l\'hai inserito). Copia la stessa chiave in config/config.php oppure reinserisci il segreto nel pannello.');
        }
        return $plain;
    }

    /** true se il valore è nel formato vecchio e conviene riscriverlo nel nuovo. */
    public function needsUpgrade(string $encoded): bool
    {
        return !str_starts_with($encoded, self::PREFIX) && self::opensslReady();
    }

    private function assertAvailable(): void
    {
        if (($p = $this->problem()) !== null) {
            throw new AiException('Salvataggio cifrato non disponibile: ' . $p);
        }
    }

    private static function opensslReady(): bool
    {
        return function_exists('openssl_encrypt') && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    private static function sodiumReady(): bool
    {
        return function_exists('sodium_crypto_secretbox_open');
    }

    /** Legge config/app.key, oppure la crea al primo uso. */
    private static function loadOrCreateKeyFile(string $file, string &$problem): ?string
    {
        if (is_file($file)) {
            $hex = trim((string) @file_get_contents($file));
            if (preg_match('~^[0-9a-f]{64}$~i', $hex)) {
                return hex2bin($hex);
            }
            $problem = "Il file $file non contiene una chiave valida: cancellalo (verrà ricreato) e reinserisci i segreti.";
            return null;
        }
        $hex = bin2hex(random_bytes(32));
        if (@file_put_contents($file, $hex . "\n", LOCK_EX) === false) {
            $problem = "Non riesco a creare $file: la cartella config/ deve essere scrivibile dall'utente con cui gira Apache, oppure compila security.app_key in config.php.";
            return null;
        }
        @chmod($file, 0600);
        return hex2bin($hex);
    }
}
