<?php
/**
 * Front controller: unico file PHP raggiungibile dal browser.
 * Tutto il resto (src, config, storage, views) sta fuori dalla webroot.
 */
declare(strict_types=1);

use Ramingo\Controllers as C;
use Ramingo\Core\Request;
use Ramingo\Core\Router;
use Ramingo\Core\Session;

/** @var Ramingo\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

// Intestazioni di sicurezza
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self' https://login.microsoftonline.com");

Session::start((bool) $app->config()->get('app.https', false));
$request = Request::fromGlobals((string) $app->config()->get('app.base_path', ''));

$router = (new Router())
    ->get('/', [C\CatalogController::class, 'index'])
    ->get('/fonti', [C\CatalogController::class, 'sources'])
    ->get('/cerca', [C\SearchController::class, 'index'])
    ->get('/api/search', [C\SearchController::class, 'api'])
    ->get('/report/{id}', [C\ReportController::class, 'show'])
    ->post('/report/{id}/definizione', [C\ReportController::class, 'uploadDefinition'])
    ->get('/dashboard/{id}', [C\DashboardController::class, 'show'])
    ->post('/annotazioni/{type}/{id}', [C\AnnotationController::class, 'save'])
    ->post('/annotazioni/{type}/{id}/approva', [C\AnnotationController::class, 'approve'])
    ->post('/ai/report/{id}/spiega', [C\AiController::class, 'explainReport'])
    ->post('/ai/chiedi', [C\AiController::class, 'ask'])
    ->get('/ai/impostazioni', [C\AiController::class, 'settings'])
    ->post('/ai/impostazioni', [C\AiController::class, 'save'])
    ->post('/ai/impostazioni/elimina', [C\AiController::class, 'delete'])
    ->post('/ai/prova', [C\AiController::class, 'test'])
    ->get('/admin', [C\AdminController::class, 'index'])
    ->get('/admin/powerbi', [C\PowerBiAdminController::class, 'show'])
    ->post('/admin/powerbi', [C\PowerBiAdminController::class, 'save'])
    ->post('/admin/powerbi/prova', [C\PowerBiAdminController::class, 'test'])
    ->post('/admin/powerbi/modalita', [C\PowerBiAdminController::class, 'switchMode'])
    ->post('/admin/powerbi/sync', [C\PowerBiAdminController::class, 'sync'])
    ->get('/admin/glossario', [C\GlossaryController::class, 'index'])
    ->post('/admin/glossario', [C\GlossaryController::class, 'save'])
    ->get('/admin/categorie', [C\CategoryController::class, 'index'])
    ->post('/admin/categorie/crea', [C\CategoryController::class, 'create'])
    ->post('/admin/categorie/modifica', [C\CategoryController::class, 'update'])
    ->post('/admin/categorie/sposta', [C\CategoryController::class, 'move'])
    ->post('/admin/categorie/elimina', [C\CategoryController::class, 'delete'])
    ->post('/admin/categorie/assegna', [C\CategoryController::class, 'assign'])
    ->get('/login', [C\AuthController::class, 'login'])
    ->get('/auth/callback', [C\AuthController::class, 'callback'])
    ->post('/logout', [C\AuthController::class, 'logout']);

try {
    $router->dispatch($request, $app);
} catch (Throwable $e) {
    $app->logger()->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    (new C\ErrorController($app, $request))->serverError($e);
}
