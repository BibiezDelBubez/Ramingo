<?php
declare(strict_types=1);

namespace Ramingo\Core;

/** Richiesta HTTP corrente, normalizzata. */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        public readonly string $ip,
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($path, '/'),
            $_GET,
            $_POST,
            $_SERVER['REMOTE_ADDR'] ?? '',
        );
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $v = $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $v = $this->body[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** File caricato ($_FILES), oppure null. */
    public function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return is_array($f) && is_string($f['name'] ?? null) ? $f : null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with($this->path, '/api/');
    }
}
