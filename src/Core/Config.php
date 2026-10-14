<?php
declare(strict_types=1);

namespace Ramingo\Core;

/** Configurazione in sola lettura con accesso a "punti": get('powerbi.tenant_id'). */
final class Config
{
    public function __construct(private readonly array $items)
    {
    }

    public static function fromFile(string $file): self
    {
        $items = require $file;
        if (!is_array($items)) {
            throw new \RuntimeException("Il file di configurazione $file deve restituire un array.");
        }
        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
