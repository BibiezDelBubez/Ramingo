<?php
declare(strict_types=1);

namespace Ramingo\Services\Auth;

use Ramingo\Core\Session;
use Ramingo\Services\Http\HttpClient;

/**
 * Login con account Microsoft aziendale (OpenID Connect, authorization code + PKCE).
 *
 * Chiede SOLO openid/profile/email: Ramingo non agisce su Power BI per conto
 * dell'utente. Serve a sapere chi è e a quali gruppi appartiene, per segnare
 * i report "probabilmente accessibili". I permessi veri li applica Power BI
 * quando l'utente apre il link.
 *
 * L'id_token arriva direttamente dal token endpoint su TLS (code flow, client
 * confidenziale): secondo OIDC Core §3.1.3.7 la verifica della firma può essere
 * sostituita dalla validazione TLS; si validano comunque iss, aud, tid, exp e nonce.
 */
final class EntraAuth
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly array $auth,
        private readonly string $tenantId,
        private readonly string $authority,
    ) {
    }

    public function authorizationUrl(): string
    {
        $state    = bin2hex(random_bytes(16));
        $nonce    = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        Session::set('oidc', ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier]);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $this->endpoint('authorize') . '?' . http_build_query([
            'client_id'             => $this->auth['client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => $this->auth['redirect_uri'],
            'response_mode'         => 'query',
            'scope'                 => 'openid profile email',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /** @return array{oid:string,name:string,email:?string,groups:string[],groups_complete:bool} */
    public function handleCallback(?string $code, ?string $state): array
    {
        $oidc = Session::pull('oidc');
        if (!$code || !$state || !is_array($oidc) || !hash_equals($oidc['state'], $state)) {
            throw new \RuntimeException('Risposta di login non valida (state).');
        }

        $res = $this->http->postForm($this->endpoint('token'), [
            'client_id'     => $this->auth['client_id'],
            'client_secret' => $this->auth['client_secret'],
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->auth['redirect_uri'],
            'code_verifier' => $oidc['verifier'],
            'scope'         => 'openid profile email',
        ]);
        if ($res['status'] !== 200 || empty($res['json']['id_token'])) {
            throw new \RuntimeException('Login non riuscito: ' . ($res['json']['error_description'] ?? 'errore sconosciuto'));
        }

        $claims = $this->decodeJwtPayload($res['json']['id_token']);
        $this->validateClaims($claims, $oidc['nonce']);

        // Se l'utente è in troppi gruppi, Entra non li elenca ("overage"): lo segnaliamo.
        $overage = isset($claims['_claim_names']['groups']);

        return [
            'oid'             => (string) $claims['oid'],
            'name'            => (string) ($claims['name'] ?? $claims['preferred_username'] ?? 'Utente'),
            'email'           => $claims['email'] ?? $claims['preferred_username'] ?? null,
            'groups'          => array_values(array_filter((array) ($claims['groups'] ?? []), 'is_string')),
            'groups_complete' => !$overage && isset($claims['groups']),
        ];
    }

    public function logoutUrl(string $returnTo): string
    {
        return $this->endpoint('logout') . '?' . http_build_query(['post_logout_redirect_uri' => $returnTo]);
    }

    private function endpoint(string $name): string
    {
        return rtrim($this->authority, '/') . '/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/' . $name;
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('id_token malformato.');
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('id_token illeggibile.');
        }
        return $data;
    }

    private function validateClaims(array $c, string $nonce): void
    {
        $expectedIss = rtrim($this->authority, '/') . '/' . $this->tenantId . '/v2.0';
        $checks = [
            'aud'   => ($c['aud'] ?? null) === $this->auth['client_id'],
            'tid'   => strcasecmp((string) ($c['tid'] ?? ''), $this->tenantId) === 0,
            'iss'   => strcasecmp((string) ($c['iss'] ?? ''), $expectedIss) === 0,
            'exp'   => (int) ($c['exp'] ?? 0) > time() - 60,
            'nonce' => hash_equals($nonce, (string) ($c['nonce'] ?? '')),
            'oid'   => !empty($c['oid']),
        ];
        foreach ($checks as $claim => $ok) {
            if (!$ok) {
                throw new \RuntimeException("id_token non valido (controllo $claim).");
            }
        }
    }
}
