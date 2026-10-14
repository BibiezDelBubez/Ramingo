<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class SyncRunRepository extends Repository
{
    public function start(string $mode, string $startedAt): int
    {
        $this->db->run("INSERT INTO sync_runs (mode, started_at, status) VALUES (?, ?, 'running')", [$mode, $startedAt]);
        return (int) $this->db->lastInsertId();
    }

    public function finish(int $id, string $status, array $stats, ?string $error = null): void
    {
        $this->db->run(
            'UPDATE sync_runs SET finished_at = ?, status = ?, stats_json = ?, error = ? WHERE id = ?',
            [now_iso(), $status, self::json($stats), $error, $id]
        );
    }

    public function lastSuccessful(): ?array
    {
        return $this->db->one("SELECT * FROM sync_runs WHERE status = 'ok' ORDER BY id DESC LIMIT 1");
    }

    public function recent(int $limit = 20): array
    {
        $rows = $this->db->all('SELECT * FROM sync_runs ORDER BY id DESC LIMIT ' . (int) $limit);
        foreach ($rows as &$r) {
            $r['stats'] = json_decode((string) $r['stats_json'], true) ?: [];
        }
        return $rows;
    }
}
