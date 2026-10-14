<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

final class ErrorController extends BaseController
{
    public function before(): void
    {
        // nessun controllo: deve funzionare sempre
    }

    public function notFound(): void
    {
        $this->abort(404, 'Pagina non trovata.');
    }

    public function serverError(\Throwable $e): void
    {
        $msg = $this->app->config()->get('app.debug') ? $e->getMessage() : 'Si è verificato un errore. È stato registrato nei log.';
        $this->abort(500, $msg);
    }
}
