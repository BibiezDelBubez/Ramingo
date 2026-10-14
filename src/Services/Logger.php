<?php
declare(strict_types=1);

namespace Ramingo\Services;

/** Log giornaliero su file. Non registrare MAI token, segreti o password. */
final class Logger
{
    public function __construct(private readonly string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARN', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        file_put_contents($this->dir . '/ramingo-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $line);
        }
    }
}
