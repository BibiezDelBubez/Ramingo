<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Services\Auth\EntraAuth;

final class AuthController extends BaseController
{
    protected function requiresLogin(): bool
    {
        return false;
    }

    /** GET /login */
    public function login(): void
    {
        if ($this->app->config()->get('auth.mode') !== 'entra') {
            $this->redirect(url());
        }
        $this->redirect($this->entra()->authorizationUrl());
    }

    /** GET /auth/callback */
    public function callback(): void
    {
        if ($error = $this->request->query('error')) {
            $this->abort(401, 'Accesso non riuscito: ' . $this->request->query('error_description', $error));
        }
        try {
            $user = $this->entra()->handleCallback($this->request->query('code'), $this->request->query('state'));
        } catch (\Throwable $e) {
            $this->app->logger()->warning('Login fallito: ' . $e->getMessage());
            $this->abort(401, 'Accesso non riuscito. Riprova.');
        }
        Session::regenerate(); // anti session fixation
        Session::set('user', $user);
        $to = (string) Session::pull('after_login', url());
        $this->redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : url());
    }

    /** POST /logout */
    public function logout(): void
    {
        Session::destroy();
        if ($this->app->config()->get('auth.mode') === 'entra') {
            $home = preg_replace('~/auth/callback$~', '/', (string) $this->app->config()->get('auth.redirect_uri'));
            $this->redirect($this->entra()->logoutUrl((string) $home));
        }
        $this->redirect(url());
    }

    private function entra(): EntraAuth
    {
        $cfg = $this->app->config();
        return new EntraAuth($this->app->http(), (array) $cfg->get('auth'), (string) ($this->app->powerBiSettings()->all()['tenant_id'] ?? ''), (string) $cfg->get('powerbi.authority'));
    }
}
