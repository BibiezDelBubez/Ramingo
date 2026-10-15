<?php
declare(strict_types=1);

namespace Ramingo\Models;

/**
 * Gruppi di sinonimi per la ricerca («vendite, fatturato, ricavi»).
 * La prima volta si parte dai gruppi di config/synonyms.php; da lì in poi comanda il database.
 */
final class SynonymRepository extends Repository
{
    private const SEEDED = 'synonyms.seeded';

    /** @return array<int, array{id:int, words:string[]}> */
    public function all(array $fileGroups = []): array
    {
        $this->seed($fileGroups);
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'words' => self::split($r['words'])],
            $this->db->all('SELECT id, words FROM synonym_groups ORDER BY sort_order, id'));
    }

    /** Solo le parole, nel formato atteso da QueryInterpreter. @return array<int, string[]> */
    public function groups(array $fileGroups = []): array
    {
        return array_values(array_filter(array_column($this->all($fileGroups), 'words'), static fn ($g) => count($g) >= 2));
    }

    /**
     * Sostituisce tutti i gruppi. Ogni gruppo: elenco di parole (il primo è il concetto mostrato).
     * @param array<int, string[]> $groups
     */
    public function replaceAll(array $groups, string $by): void
    {
        $this->db->transaction(function () use ($groups, $by): void {
            $this->db->exec('DELETE FROM synonym_groups');
            foreach (array_values($groups) as $i => $words) {
                $this->db->run('INSERT INTO synonym_groups (words, sort_order, updated_by, updated_at) VALUES (?, ?, ?, ?)',
                    [implode(', ', $words), $i, $by, now_iso()]);
            }
            $this->markSeeded();
        });
    }

    /** «vendite, Fatturato , ricavi,,» → ['vendite', 'fatturato', 'ricavi'] (minuscolo, senza doppioni). */
    public static function split(string $text): array
    {
        $out = [];
        foreach (explode(',', $text) as $w) {
            $w = mb_strtolower(trim(preg_replace('~\s+~u', ' ', $w)));
            if ($w !== '' && mb_strlen($w) <= 60 && !in_array($w, $out, true)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    private function seed(array $fileGroups): void
    {
        if ($this->db->value('SELECT value FROM settings WHERE key = ?', [self::SEEDED]) !== null) {
            return;
        }
        $this->replaceAll(array_map(static fn ($g) => self::split(implode(',', (array) $g)), $fileGroups), 'config/synonyms.php');
    }

    private function markSeeded(): void
    {
        $this->db->upsert('settings', ['key' => self::SEEDED, 'value' => now_iso()], ['key']);
    }
}
