<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Services\Search\Text;

/** Registro anonimo delle ricerche (nessun dato su chi cerca). Conservato per un anno. */
final class SearchLogRepository extends Repository
{
    private const KEEP_DAYS = 365;

    public function log(string $query, int $results): void
    {
        $norm = Text::normalize($query);
        if ($norm === '') {
            return;
        }
        $this->db->run('INSERT INTO search_log (query, normalized, results, created_at) VALUES (?, ?, ?, ?)',
            [mb_substr($query, 0, 300), mb_substr($norm, 0, 300), $results, now_iso()]);
        if (random_int(1, 50) === 1) { // pulizia ogni tanto, senza un'attività pianificata apposta
            $this->db->run('DELETE FROM search_log WHERE created_at < ?', [gmdate('Y-m-d\TH:i:s\Z', time() - self::KEEP_DAYS * 86400)]);
        }
    }

    /**
     * Ricerche raggruppate per testo (normalizzato) negli ultimi $days giorni.
     * @param bool $onlyEmpty solo quelle che l'ultima volta non hanno trovato niente
     * @return array<int, array{normalized:string, example:string, n:int, last:string, results:int}>
     */
    public function grouped(int $days, bool $onlyEmpty, int $limit = 100): array
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
        $rows = $this->db->all(
            'SELECT normalized, MAX(query) AS example, COUNT(*) AS n, MAX(created_at) AS last,
                    (SELECT results FROM search_log l2 WHERE l2.normalized = l.normalized ORDER BY created_at DESC LIMIT 1) AS results
               FROM search_log l WHERE created_at >= ?
           GROUP BY normalized ORDER BY n DESC, last DESC',
            [$since]
        );
        if ($onlyEmpty) {
            $rows = array_values(array_filter($rows, static fn ($r) => (int) $r['results'] === 0));
        }
        return array_slice($rows, 0, $limit);
    }

    /** @return array{total:int, empty:int} negli ultimi $days giorni */
    public function stats(int $days): array
    {
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
        $row = $this->db->one('SELECT COUNT(*) AS total, SUM(results = 0) AS empty FROM search_log WHERE created_at >= ?', [$since]) ?? [];
        return ['total' => (int) ($row['total'] ?? 0), 'empty' => (int) ($row['empty'] ?? 0)];
    }
}
