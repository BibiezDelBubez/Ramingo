<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

use Ramingo\Services\Http\HttpClient;

/** Parte comune agli adattatori HTTP: chiamata, gestione errori, messaggi leggibili. */
abstract class HttpAiProvider implements AiProvider
{
    public function __construct(
        protected readonly HttpClient $http,
        protected readonly AiConfig $cfg,
    ) {
    }

    protected function post(string $url, array $payload, array $headers): array
    {
        try {
            $res = $this->http->postJson($url, $payload, $headers);
        } catch (\Throwable $e) {
            throw new AiException('Impossibile raggiungere il servizio AI (manca internet, proxy o indirizzo sbagliato).');
        }
        if ($res['status'] >= 200 && $res['status'] < 300 && is_array($res['json'])) {
            return $res['json'];
        }
        $detail = $res['json']['error']['message'] ?? $res['json']['message'] ?? null;
        $text = is_string($detail) ? mb_strtolower($detail) : '';
        $hint = match (true) {
            $res['status'] === 402, str_contains($text, 'credit'), str_contains($text, 'quota'), str_contains($text, 'billing')
                => 'crediti esauriti o pagamento non attivo',
            $res['status'] === 401, $res['status'] === 403 => 'chiave non valida o senza permessi',
            $res['status'] === 404 => 'modello, deployment o indirizzo inesistente',
            $res['status'] === 429 => 'limite di utilizzo raggiunto',
            default => 'errore del servizio',
        };
        throw new AiException(sprintf('Il servizio AI ha risposto HTTP %d (%s)%s', $res['status'], $hint,
            is_string($detail) ? ': ' . mb_substr($detail, 0, 200) : '.'));
    }
}
