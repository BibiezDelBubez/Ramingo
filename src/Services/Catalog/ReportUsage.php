<?php
declare(strict_types=1);

namespace Ramingo\Services\Catalog;

/**
 * Quali tabelle, colonne e misure usa DAVVERO un report, ricavato dai suoi visual.
 * Serve quando molti report condividono lo stesso modello semantico (es. un modello «Master»):
 * senza questo, tutti mostrerebbero l'elenco completo del modello.
 */
final class ReportUsage
{
    /** @var array<string, true> */
    private array $entities = [];
    /** @var array<string, true> "tabella|campo" */
    private array $fields = [];
    /** @var array<string, true> nomi di misura (le misure sono uniche nel modello) */
    private array $measures = [];

    private function __construct(private readonly bool $known)
    {
    }

    /** @param array<string, array> $visualsByPage come restituito da ReportRepository::visualsByPage */
    public static function fromVisuals(array $visualsByPage): self
    {
        $all = array_merge(...array_values($visualsByPage ?: [[]]));
        $withFields = array_filter($all, static fn ($v) => !empty($v['fields']));
        $u = new self((bool) $withFields);
        foreach ($withFields as $v) {
            foreach ($v['fields'] as $f) {
                $e = mb_strtolower($f['entity']);
                $p = mb_strtolower($f['property']);
                if ($e !== '') {
                    $u->entities[$e] = true;
                }
                $u->fields[$e . '|' . $p] = true;
                if ($f['kind'] !== 'column') {
                    $u->measures[$p] = true; // "unknown" può essere una misura
                }
            }
        }
        return $u;
    }

    /** @param array<int, array{fields_json:?string}> $rows righe di report_visuals */
    public static function fromRows(array $rows): self
    {
        return self::fromVisuals(['all' => array_map(static fn ($r) => [
            'fields' => array_map([self::class, 'normalizeField'], json_decode((string) $r['fields_json'], true) ?: []),
        ], $rows)]);
    }

    /** Ramingo conosce i campi usati da questo report? (altrimenti: nessun filtro) */
    public function known(): bool
    {
        return $this->known;
    }

    public function usesTable(string $table): bool
    {
        return !$this->known || isset($this->entities[mb_strtolower($table)]);
    }

    public function usesMeasure(string $measure): bool
    {
        return !$this->known || isset($this->measures[mb_strtolower($measure)]);
    }

    public function usesColumn(string $table, string $column): bool
    {
        return !$this->known || isset($this->fields[mb_strtolower($table) . '|' . mb_strtolower($column)]);
    }

    /**
     * Normalizza un campo salvato (formato nuovo = array, formato vecchio = stringa "Tabella.Campo").
     * @return array{entity:string, property:string, kind:string, label:string}
     */
    public static function normalizeField(mixed $f): array
    {
        if (is_array($f)) {
            return [
                'entity'   => (string) ($f['entity'] ?? ''),
                'property' => (string) ($f['property'] ?? ''),
                'kind'     => (string) ($f['kind'] ?? 'unknown'),
                'label'    => (string) ($f['label'] ?? $f['property'] ?? ''),
            ];
        }
        $s = (string) $f;
        if (preg_match('~^(?:\w+\()?([^.()]+)\.([^()]+?)\)?$~u', $s, $m)) {
            return ['entity' => $m[1], 'property' => $m[2], 'kind' => 'unknown', 'label' => $m[2]];
        }
        return ['entity' => '', 'property' => $s, 'kind' => 'unknown', 'label' => $s];
    }
}
