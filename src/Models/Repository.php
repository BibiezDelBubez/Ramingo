<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/** Base dei repository (il "Model" dell'MVC): unico punto di accesso ai dati. */
abstract class Repository
{
    public function __construct(protected readonly Database $db)
    {
    }

    /** Marca come eliminati gli oggetti di un workspace non visti nella scansione corrente. */
    protected function markMissing(string $table, string $workspaceId, string $runStart): int
    {
        return $this->db->run(
            "UPDATE $table SET is_deleted = 1 WHERE workspace_id = ? AND last_seen_at < ? AND is_deleted = 0",
            [$workspaceId, $runStart]
        )->rowCount();
    }

    protected static function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
