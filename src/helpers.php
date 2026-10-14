<?php
/**
 * Funzioni globali minime, usate soprattutto nelle viste.
 * Tenute volutamente poche: tutto il resto vive nelle classi.
 */
declare(strict_types=1);

/** Escape HTML: da usare per QUALSIASI valore stampato in una vista. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL interno all'app, rispettando il base path configurato. */
function url(string $path = '', array $query = []): string
{
    $base = rtrim((string) Ramingo\Core\App::instance()->config()->get('app.base_path', ''), '/');
    $url  = $base . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    return $query ? $url . '?' . http_build_query($query) : $url;
}

/** URL di un asset statico in public/assets. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Data leggibile in italiano; restituisce '—' se assente. */
function fmt_date(?string $iso, bool $withTime = false): string
{
    if (!$iso) {
        return '—';
    }
    try {
        $d = new DateTimeImmutable($iso);
        $d = $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $d->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    } catch (Throwable) {
        return e($iso);
    }
}

/** Timestamp ISO-8601 UTC per il database. */
function now_iso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}
