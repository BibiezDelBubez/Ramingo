<?php
declare(strict_types=1);

namespace Ramingo\Domain;

/**
 * Un valore accompagnato dalla sua provenienza.
 * Le viste non stampano mai un dato "nudo" quando la provenienza conta:
 * ricevono un Sourced e lo rendono con il partial source_badge.
 */
final class Sourced
{
    public function __construct(
        public readonly mixed $value,
        public readonly Source $source,
        public readonly ?string $author = null,
        public readonly ?string $at = null,
        public readonly ?string $generator = null,
    ) {
    }

    public static function pbi(mixed $value): self
    {
        return new self($value, Source::PowerBi);
    }

    public static function def(mixed $value): self
    {
        return new self($value, Source::Definition);
    }

    public static function fromAnnotation(array $row): self
    {
        return new self(
            $row['value'],
            Source::from($row['source']),
            $row['author'] ?? null,
            $row['created_at'] ?? null,
            $row['generator'] ?? null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->value === null || $this->value === '' || $this->value === [];
    }

    /** Primo valore non vuoto in ordine di priorità (es. manuale > Power BI > auto). */
    public static function firstFilled(?self ...$candidates): ?self
    {
        foreach ($candidates as $c) {
            if ($c !== null && !$c->isEmpty()) {
                return $c;
            }
        }
        return null;
    }
}
