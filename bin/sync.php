<?php
/**
 * Sincronizza il catalogo con Power BI.
 *
 *   php bin\sync.php --mode=full          tutto il tenant (es. ogni notte)
 *   php bin\sync.php --mode=incremental   solo i workspace modificati (es. ogni ora)
 *
 * Da pianificare con l'Utilità di pianificazione di Windows (vedi README).
 * Con powerbi.mode = 'fixture' usa i dati di esempio.
 */
declare(strict_types=1);

$app  = require __DIR__ . '/cli.php';
$mode = cli_option('mode', 'incremental');
if (!in_array($mode, ['full', 'incremental'], true)) {
    fwrite(STDERR, "Modo non valido: usa --mode=full oppure --mode=incremental\n");
    exit(2);
}

$lock = fopen(RAMINGO_ROOT . '/storage/sync.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Un'altra sincronizzazione è già in corso.\n");
    exit(1);
}

try {
    $stats = $app->catalogSync()->run($mode);
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRORE: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
}
