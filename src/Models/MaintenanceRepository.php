<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/** Operazioni straordinarie sul catalogo. */
final class MaintenanceRepository extends Repository
{
    /**
     * Svuota il catalogo (es. passaggio dai dati di esempio al tenant reale).
     * Restano: aree aziendali, impostazioni, chiavi AI, registro richieste AI.
     */
    public function purgeCatalog(): void
    {
        $this->db->transaction(function (Database $db): void {
            foreach (['annotations', 'tags', 'artifact_users', 'search_docs', 'sync_runs', 'workspaces'] as $table) {
                $db->exec("DELETE FROM $table"); // workspaces → a cascata report, modelli, dashboard, pagine, schema
            }
            if ((bool) $db->value("SELECT 1 FROM sqlite_master WHERE name = 'search_fts'")) {
                $db->exec('DELETE FROM search_fts');
            }
        });
    }
}
