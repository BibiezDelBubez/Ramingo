<?php
declare(strict_types=1);

namespace Ramingo\Services\Http;

use Ramingo\Services\Logger;

/**
 * Client HTTP basato su cURL (incluso in XAMPP).
 * Gestisce proxy aziendale, bundle dei certificati e retry su 429/5xx.
 */
final class HttpClient
{
    public function __construct(private readonly array $options, private readonly Logger $logger)
    {
    }

    /** @return array{status:int, body:string, json:mixed, headers:array<string,string>} */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $maxRetries = (int) ($this->options['max_retries'] ?? 3);
        for ($attempt = 0; ; $attempt++) {
            $response = $this->send($method, $url, $headers, $body);
            $retryable = $response['status'] === 429 || $response['status'] >= 500;
            if (!$retryable || $attempt >= $maxRetries) {
                return $response;
            }
            $wait = (int) ($response['headers']['retry-after'] ?? (2 ** $attempt * 5));
            $this->logger->warning('HTTP ' . $response['status'] . ', nuovo tentativo', ['url' => $this->safeUrl($url), 'wait' => $wait]);
            sleep(min($wait, 120));
        }
    }

    public function getJson(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, $headers + ['Accept' => 'application/json']);
    }

    public function postJson(string $url, array $payload, array $headers = []): array
    {
        return $this->request('POST', $url, $headers + [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ], json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function postForm(string $url, array $fields): array
    {
        return $this->request('POST', $url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept'       => 'application/json',
        ], http_build_query($fields));
    }

    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        $responseHeaders = [];
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->options['timeout'] ?? 60),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true, // mai disattivare: usare ca_bundle se serve
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => array_map(static fn ($k, $v) => "$k: $v", array_keys($headers), $headers),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if (!empty($this->options['ca_bundle'])) {
            $opts[CURLOPT_CAINFO] = $this->options['ca_bundle'];
        }
        if (!empty($this->options['proxy'])) {
            $opts[CURLOPT_PROXY] = $this->options['proxy'];
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new HttpException('Errore di rete verso ' . $this->safeUrl($url) . ': ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status'  => $status,
            'body'    => (string) $raw,
            'json'    => json_decode((string) $raw, true),
            'headers' => $responseHeaders,
        ];
    }

    private function safeUrl(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }
}
