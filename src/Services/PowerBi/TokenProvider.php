<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

use Ramingo\Services\Http\HttpClient;
use Ramingo\Services\Http\HttpException;

/**
 * Token applicativo (OAuth2 client credentials) per il service principal "Ramingo-Sync".
 * Il token resta solo in memoria per la durata del processo di sincronizzazione.
 */
final class TokenProvider
{
    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(private readonly HttpClient $http, private readonly array $cfg)
    {
    }

    public function token(): string
    {
        if ($this->token !== null && time() < $this->expiresAt - 120) {
            return $this->token;
        }
        foreach (['tenant_id', 'client_id', 'client_secret'] as $k) {
            if (empty($this->cfg[$k])) {
                throw new HttpException("Configurazione Power BI incompleta: manca powerbi.$k");
            }
        }
        $url = rtrim($this->cfg['authority'], '/') . '/' . rawurlencode($this->cfg['tenant_id']) . '/oauth2/v2.0/token';
        $res = $this->http->postForm($url, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
            'scope'         => $this->cfg['scope'],
        ]);
        if ($res['status'] !== 200 || empty($res['json']['access_token'])) {
            $msg = $res['json']['error_description'] ?? ('HTTP ' . $res['status']);
            throw new HttpException('Impossibile ottenere il token Power BI: ' . strtok((string) $msg, "\r\n"), $res['status']);
        }
        $this->token     = $res['json']['access_token'];
        $this->expiresAt = time() + (int) ($res['json']['expires_in'] ?? 3600);
        return $this->token;
    }
}
