<?php
declare(strict_types=1);

namespace Ramingo\Services\Auth;

use Ramingo\Core\Config;
use Ramingo\Core\Request;
use Ramingo\Core\Session;

/** L'utente che sta usando Ramingo in questa richiesta. */
final class CurrentUser
{
    private function __construct(
        public readonly ?string $oid,
        public readonly string $name,
        public readonly ?string $email,
        /** @var string[] object id dei gruppi di sicurezza Entra (claim "groups") */
        public readonly array $groups,
        public readonly bool $groupsComplete,
        private readonly bool $admin,
        private readonly bool $authenticated,
        /** Perché è amministratore: 'local' (stessa macchina o IP in elenco), 'password', 'email' oppure null. */
        public readonly ?string $adminVia = null,
    ) {
    }

    /** Chiave di sessione impostata dopo la password di amministrazione giusta. */
    public const SESSION_ADMIN = 'admin_password_ok';

    public static function resolve(Config $config, Request $request): self
    {
        if ($config->get('auth.mode', 'none') !== 'entra') {
            // Senza login: amministratore automatico se il browser è sulla stessa macchina del server
            // (o su un IP in auth.admin_ips); da un altro PC serve la password di amministrazione.
            $via = self::isLocal($request) || in_array($request->ip, (array) $config->get('auth.admin_ips', []), true)
                ? 'local'
                : (Session::get(self::SESSION_ADMIN) === true ? 'password' : null);
            return new self(null, 'Utente locale', null, [], false, $via !== null, true, $via);
        }

        $u = Session::get('user');
        if (!is_array($u)) {
            return new self(null, 'Ospite', null, [], false, false, false);
        }
        $admins = array_map('strtolower', (array) $config->get('auth.admin_emails', []));
        return new self(
            $u['oid'],
            $u['name'],
            $u['email'],
            $u['groups'] ?? [],
            (bool) ($u['groups_complete'] ?? false),
            $isAdmin = in_array(strtolower((string) $u['email']), $admins, true),
            true,
            $isAdmin ? 'email' : null,
        );
    }

    /** Browser e server sulla stessa macchina: indirizzo di loopback, o stesso indirizzo da entrambe le parti. */
    private static function isLocal(Request $request): bool
    {
        $ip = $request->ip;
        if (in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
            return true;
        }
        return $ip !== '' && $request->serverIp !== '' && $ip === $request->serverIp;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /** Identità note dell'utente: lui stesso + i suoi gruppi. Vuoto se non identificato. */
    public function principalIds(): array
    {
        return $this->oid ? array_values(array_unique([$this->oid, ...$this->groups])) : [];
    }

    public function displayIdentity(): string
    {
        return $this->email ?: $this->name;
    }
}
