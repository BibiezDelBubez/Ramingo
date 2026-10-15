<?php
/**
 * Crea / aggiorna il database.  Uso:  php bin\migrate.php [--seed-areas]
 */
declare(strict_types=1);

use Ramingo\Models\SearchRepository;

$app = require __DIR__ . '/cli.php';
$db  = $app->db();

// Colonne aggiunte dopo la prima versione (SQLite non ha "ADD COLUMN IF NOT EXISTS")
$addColumns = [
    ['areas', 'parent_id', 'INTEGER REFERENCES areas(id) ON DELETE SET NULL'],
    ['reports', 'definition_fetched_at', 'TEXT'],
    ['search_docs', 'tech', "TEXT NOT NULL DEFAULT ''"],
    ['reports', 'pages_error', 'TEXT'],       // motivo dell'ultimo rifiuto di Power BI (si riprova)
    ['reports', 'definition_error', 'TEXT'],
];
$tableExists = static fn (string $t) => (bool) $db->value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]);
foreach ($addColumns as [$table, $column, $definition]) {
    if ($tableExists($table) && !in_array($column, array_column($db->all("PRAGMA table_info($table)"), 'name'), true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        echo "Aggiunta colonna $table.$column\n";
    }
}

$db->exec((string) file_get_contents(RAMINGO_ROOT . '/database/schema.sql'));
foreach ($addColumns as [$table, $column, $definition]) { // database nuovo: tabella appena creata
    if (!in_array($column, array_column($db->all("PRAGMA table_info($table)"), 'name'), true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}
// Letture fallite prima che esistesse il nuovo tentativo: si segnano come «da riprovare»
$db->exec("UPDATE reports SET definition_error = 'da riprovare' WHERE definition_fetched_at IS NOT NULL AND definition_error IS NULL
           AND NOT EXISTS (SELECT 1 FROM report_visuals v WHERE v.report_id = reports.id)");
$db->exec("UPDATE reports SET pages_error = 'da riprovare' WHERE pages_fetched_at IS NOT NULL AND pages_error IS NULL
           AND NOT EXISTS (SELECT 1 FROM report_pages p WHERE p.report_id = reports.id)");

// Icona predefinita delle categorie: non più la cartella, per non confonderle con i workspace di Power BI
$db->exec("UPDATE areas SET icon = 'fa-solid fa-shapes' WHERE icon = 'fa-solid fa-folder' OR icon IS NULL OR icon = ''");
echo "Schema applicato.\n";

// Indice full-text della versione precedente (senza la colonna «tech»): si ricrea e si ricostruisce
$rebuild = false;
if ($tableExists('search_fts') && !in_array('tech', array_column($db->all('PRAGMA table_info(search_fts)'), 'name'), true)) {
    $db->exec('DROP TABLE search_fts');
    $rebuild = true;
}
echo SearchRepository::createFtsTable($db)
    ? "Ricerca full-text: FTS5 attivo.\n"
    : "Ricerca full-text: FTS5 non disponibile, uso LIKE (funziona, solo meno raffinato).\n";
if ($rebuild) {
    echo 'Indice di ricerca ricostruito: ' . $app->indexer()->rebuildAll() . " elementi.\n";
}

if (in_array('--seed-areas', $argv, true) && !(int) $db->value('SELECT COUNT(*) FROM areas')) {
    $areas = [
        ['Commerciale e Vendite', 'fa-solid fa-handshake'],
        ['Amministrazione e Finanza', 'fa-solid fa-coins'],
        ['Operations e Logistica', 'fa-solid fa-truck'],
        ['Produzione', 'fa-solid fa-industry'],
        ['Risorse Umane', 'fa-solid fa-people-group'],
        ['Direzione', 'fa-solid fa-compass'],
        ['IT e Dati', 'fa-solid fa-server'],
    ];
    $repo = $app->repo(Ramingo\Models\AreaRepository::class);
    foreach ($areas as [$name, $icon]) {
        $repo->create($name, null, $icon);
    }
    echo count($areas) . " aree di esempio create (modificabili da Amministrazione).\n";
}
