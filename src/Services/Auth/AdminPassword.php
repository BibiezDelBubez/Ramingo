<?php
declare(strict_types=1);

namespace Ramingo\Services\Auth;

use Ramingo\Core\Config;
use Ramingo\Models\SettingRepository;

/**
 * Password di amministrazione, per entrare in Amministrazione da un PC diverso da quello di Ramingo.
 *
 * - Se è stata cambiata dal pannello, è salvata come hash (password_hash) nella tabella settings.
 * - Altrimenti vale quella di config.php (auth.admin_password, all'inizio «admin»).
 * - Con auth.admin_password vuota e nessuna password dal pannello, l'accesso con password è spento:
 *   resta solo l'accesso dalla stessa macchina.
 *
 * È una protezione leggera, adatta a una rete aziendale: non sostituisce il login con l'account Microsoft.
 */
final class AdminPassword
{
    private const KEY = 'auth.admin_password_hash';
    public const MIN_LENGTH = 4;

    public function __construct(private readonly SettingRepository $settings, private readonly Config $config)
    {
    }

    public function enabled(): bool
    {
        return $this->hash() !== null || $this->fromConfig() !== '';
    }

    /** Si usa ancora la password iniziale «admin» di config.php. */
    public function isDefault(): bool
    {
        return $this->hash() === null && $this->fromConfig() === 'admin';
    }

    /** La password attuale è quella del pannello (true) o di config.php (false). */
    public function fromPanel(): bool
    {
        return $this->hash() !== null;
    }

    public function verify(string $password): bool
    {
        if ($password === '') {
            return false;
        }
        $hash = $this->hash();
        if ($hash !== null) {
            return password_verify($password, $hash);
        }
        $plain = $this->fromConfig();
        return $plain !== '' && hash_equals($plain, $password);
    }

    public function change(string $password): void
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('La password deve avere almeno ' . self::MIN_LENGTH . ' caratteri.');
        }
        $this->settings->set(self::KEY, password_hash($password, PASSWORD_DEFAULT));
    }

    /** Torna alla password di config.php. */
    public function reset(): void
    {
        $this->settings->set(self::KEY, null);
    }

    private function hash(): ?string
    {
        $h = $this->settings->get(self::KEY);
        return $h !== null && $h !== '' ? $h : null;
    }

    private function fromConfig(): string
    {
        return (string) $this->config->get('auth.admin_password', '');
    }
}
