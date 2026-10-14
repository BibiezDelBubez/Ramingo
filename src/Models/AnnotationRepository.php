<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;
use Ramingo\Domain\Source;
use Ramingo\Domain\Sourced;

/**
 * Testi scritti a mano (Source::Manual) e interpretazioni automatiche (Source::Auto).
 * Ogni salvataggio crea una nuova versione: lo storico non si perde mai.
 */
final class AnnotationRepository extends Repository
{
    /** Campi annotabili, con etichetta: unica fonte per form e viste. */
    public const FIELDS = [
        'description'    => 'Descrizione',
        'business_owner' => 'Referente di business',
        'audience'       => 'A chi serve',
        'kpi'            => 'KPI principali',
        'notes'          => 'Note d\'uso',
        'summary'        => 'Sintesi',
        'ai_description' => 'Descrizione proposta dall\'AI',
        'ai_audience'    => 'A chi serve (proposta dall\'AI)',
    ];

    public function save(string $entityType, string $entityId, string $field, string $value, Source $source, ?string $author, ?string $generator = null): bool
    {
        if (!in_array($source, [Source::Manual, Source::Auto], true)) {
            throw new \InvalidArgumentException('Le annotazioni possono essere solo manuali o automatiche.');
        }
        $value = trim($value);
        return (bool) $this->db->transaction(function (Database $db) use ($entityType, $entityId, $field, $value, $source, $author, $generator) {
            $current = $db->value(
                'SELECT value FROM annotations WHERE entity_type = ? AND entity_id = ? AND field = ? AND source = ? AND is_current = 1',
                [$entityType, $entityId, $field, $source->value]
            );
            if ($current === $value || ($current === null && $value === '')) {
                return false; // nessun cambiamento: niente nuova versione
            }
            $db->run(
                'UPDATE annotations SET is_current = 0 WHERE entity_type = ? AND entity_id = ? AND field = ? AND source = ?',
                [$entityType, $entityId, $field, $source->value]
            );
            if ($value !== '') {
                $db->run(
                    'INSERT INTO annotations (entity_type, entity_id, field, value, source, author, generator, is_current, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
                    [$entityType, $entityId, $field, $value, $source->value, $author, $generator, now_iso()]
                );
            }
            return true;
        });
    }

    /**
     * Annotazioni correnti per più oggetti in una sola query.
     * @return array<string, array<string, array<string, Sourced>>> [entityId][field][source]
     */
    public function currentFor(string $entityType, array $entityIds): array
    {
        if (!$entityIds) {
            return [];
        }
        $in = Database::placeholders($entityIds);
        $rows = $this->db->all(
            "SELECT * FROM annotations WHERE entity_type = ? AND entity_id IN ($in) AND is_current = 1",
            [$entityType, ...$entityIds]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['entity_id']][$r['field']][$r['source']] = Sourced::fromAnnotation($r);
        }
        return $out;
    }

    public function history(string $entityType, string $entityId, int $limit = 30): array
    {
        return $this->db->all(
            'SELECT * FROM annotations WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }
}
