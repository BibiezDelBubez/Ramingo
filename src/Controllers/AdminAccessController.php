<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\SettingRepository;
use Ramingo\Services\Auth\AdminPassword;
use Ramingo\Services\Auth\CurrentUser;

/**
 * Accesso ad Amministrazione senza login aziendale (auth.mode = 'none'):
 *  - dalla stessa macchina di Ramingo si è amministratori in automatico;
 *  - da un altro PC si inserisce la password di amministrazione (vale finché il browser resta aperto).
 */
final class AdminAccessController extends BaseController
{
    /** GET /admin/accesso — modulo della password, oppure (se già amministratore) come si è entrati e cambio password. */
    public function show(): void
    {
        $this->render('admin/access', [
            'title'    => 'Accesso amministratore',
            'entra'    => $this->app->config()->get('auth.mode', 'none') === 'entra',
            'password' => $this->password(),
            'ip'       => $this->request->ip,
            'noAdminLink' => true,
        ]);
    }

    /** POST /admin/accesso */
    public function login(): void
    {
        $password = $this->password();
        if ($this->app->config()->get('auth.mode', 'none') === 'entra' || !$password->enabled()) {
            $this->redirect(url('admin/accesso'));
        }
        if (!$password->verify((string) $this->request->input('password', ''))) {
            usleep(800000); // rallenta chi prova password a raffica
            $this->app->logger()->warning('Password di amministrazione sbagliata da ' . $this->request->ip);
            Session::flash('danger', 'Password sbagliata.');
            $this->redirect(url('admin/accesso'));
        }
        Session::regenerate(); // anti session fixation
        Session::set(CurrentUser::SESSION_ADMIN, true);
        $to = (string) Session::pull('after_admin', url('admin'));
        $this->redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : url('admin'));
    }

    /** POST /admin/esci — torna utente normale (solo per chi è entrato con la password). */
    public function logout(): void
    {
        Session::set(CurrentUser::SESSION_ADMIN, null);
        Session::regenerate();
        Session::flash('success', 'Sei uscito da Amministrazione.');
        $this->redirect(url());
    }

    /** POST /admin/password — cambia la password (solo amministratori). */
    public function change(): void
    {
        $this->requireAdmin();
        $password = $this->password();
        if ($this->request->input('reset') === '1') {
            $password->reset();
            Session::flash('success', 'Ora vale di nuovo la password scritta in config.php.');
            $this->redirect(url('admin/accesso'));
        }
        $new = (string) $this->request->input('new', '');
        if ($new !== (string) $this->request->input('confirm', '')) {
            Session::flash('danger', 'Le due password non sono uguali.');
            $this->redirect(url('admin/accesso'));
        }
        try {
            $password->change($new);
            Session::flash('success', 'Password di amministrazione cambiata.');
        } catch (\InvalidArgumentException $e) {
            Session::flash('danger', $e->getMessage());
        }
        $this->redirect(url('admin/accesso'));
    }

    private function password(): AdminPassword
    {
        return new AdminPassword($this->app->repo(SettingRepository::class), $this->app->config());
    }
}
