<?php
/**
 * Importa pagine e visual di un report dalla sua definizione tecnica.
 *
 *   php bin\import-definition.php --report=<id report> --path="C:\...\Vendite.pbix"
 *   php bin\import-definition.php --report=<id report> --path="C:\...\Vendite.Report"
 *
 * I dati vengono marcati come "Definizione tecnica". Le pagine lette dall'API
 * Power BI (se presenti) restano prioritarie.
 */
declare(strict_types=1);

use Ramingo\Models\ReportRepository;
use Ramingo\Services\Definition\ReportDefinitionParser;

$app      = require __DIR__ . '/cli.php';
$reportId = cli_option('report');
$path     = cli_option('path');

if (!$reportId || !$path || !file_exists($path)) {
    fwrite(STDERR, "Uso: php bin\\import-definition.php --report=<id> --path=<file .pbix | cartella .Report | report.json>\n");
    exit(2);
}
$reports = $app->repo(ReportRepository::class);
if (!$reports->exists($reportId)) {
    fwrite(STDERR, "Report $reportId non presente nel catalogo: esegui prima la sincronizzazione.\n");
    exit(1);
}

$def = (new ReportDefinitionParser())->parsePath($path);
$reports->saveDefinition($reportId, $def);
$app->insights()->refreshReport($reportId);
$app->indexer()->indexReport($reportId);

printf("Importate %d pagine e %d visual.\n", count($def['pages']), count($def['visuals']));
