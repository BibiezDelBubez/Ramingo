<?php
/**
 * Ricalcola interpretazioni automatiche e indice di ricerca senza interrogare Power BI.
 * Utile dopo aver modificato config/synonyms.php.   Uso: php bin\reindex.php
 */
declare(strict_types=1);

$app = require __DIR__ . '/cli.php';
echo 'Interpretazioni automatiche aggiornate: ' . $app->insights()->refreshAll() . "\n";
echo 'Elementi indicizzati: ' . $app->indexer()->rebuildAll() . "\n";
