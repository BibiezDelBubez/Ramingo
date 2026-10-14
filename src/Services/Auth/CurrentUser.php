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
    ) {
    }

    public static function resolve(Config $config, Request $request): self
    {
        if ($config->get('auth.mode', 'none') !== 'entra') {
            $isAdmin = in_array($request->ip, (array) $config->get('auth.admin_ips', []), true);
            return new self(null, 'Utente locale', null, [], false, $isAdmin, true);
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
            in_array(strtolower((string) $u['email']), $admins, true),
            true,
        );
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
