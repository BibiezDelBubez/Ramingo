<?php
declare(strict_types=1);

namespace Ramingo\Core;

use Ramingo\Services\Auth\CurrentUser;

/**
 * Controller base: rendering, redirect, JSON, CSRF e controlli di accesso
 * scritti una sola volta e riusati da tutti i controller.
 */
abstract class Controller
{
    protected View $view;
    protected CurrentUser $user;

    public function __construct(protected App $app, protected Request $request)
    {
        $this->view = new View(RAMINGO_ROOT . '/views');
        $this->user = CurrentUser::resolve($app->config(), $request);
    }

    /** Hook eseguito prima di ogni azione (autenticazione, CSRF sui POST). */
    public function before(): void
    {
        if ($this->requiresLogin() && !$this->user->isAuthenticated()) {
            Session::set('after_login', $_SERVER['REQUEST_URI'] ?? url());
            $this->redirect(url('login'));
        }
        if ($this->request->isPost() && !Session::verifyCsrf($this->request->input('_csrf'))) {
            $this->abort(419, 'Sessione scaduta o richiesta non valida. Ricarica la pagina e riprova.');
        }
    }

    protected function requiresLogin(): bool
    {
        return $this->app->config()->get('auth.mode', 'none') === 'entra';
    }

    protected function render(string $template, array $data = [], int $status = 200): never
    {
        http_response_code($status);
        echo $this->view->render($template, $data + [
            'user'    => $this->user,
            'flashes' => Session::flashes(),
            'csrf'    => Session::csrfToken(),
            'appName' => $this->app->config()->get('app.name', 'Ramingo'),
            'q'       => (string) $this->request->query('q', ''),
            'debug'   => (bool) $this->app->config()->get('app.debug', false),
            'aiNav'   => (bool) $this->app->config()->get('ai.enabled', false),
        ]);
        exit;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $to): never
    {
        header('Location: ' . $to, true, 303);
        exit;
    }

    protected function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Solo redirect interni: niente open redirect.
        $this->redirect($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host ? $ref : url());
    }

    protected function requireAdmin(): void
    {
        if (!$this->user->isAdmin()) {
            $this->abort(403, 'Questa sezione è riservata agli amministratori di Ramingo.');
        }
    }

    protected function abort(int $status, string $message): never
    {
        if ($this->request->wantsJson()) {
            $this->json(['error' => $message], $status);
        }
        $this->render('errors/error', ['status' => $status, 'message' => $message], $status);
    }
}
