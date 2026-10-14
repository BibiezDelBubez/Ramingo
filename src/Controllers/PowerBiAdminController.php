<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\MaintenanceRepository;
use Ramingo\Models\SyncRunRepository;
use Ramingo\Services\PowerBi\ConnectionTest;

/** Amministrazione → Collegamento Power BI: credenziali, prova, cambio modalità, sincronizzazione. */
final class PowerBiAdminController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/powerbi */
    public function show(?array $test = null): void
    {
        $s = $this->app->powerBiSettings();
        $cfg = $s->all();
        $runs = $this->app->repo(SyncRunRepository::class)->recent(5);
        $this->render('admin/powerbi', [
            'title'     => 'Collegamento Power BI',
            'mode'      => $cfg['mode'],
            'modeLocked'=> $s->source('mode') === 'config',
            'values'    => ['tenant_id' => $cfg['tenant_id'] ?? '', 'client_id' => $cfg['client_id'] ?? ''],
            'sources'   => array_combine($s::EDITABLE, array_map([$s, 'source'], $s::EDITABLE)),
            'hint'      => $s->secretHint(),
            'complete'  => $s->isComplete(),
            'canEncrypt'=> $this->app->secretBox()->available(),
            'runs'      => $runs,
            'running'   => ($runs[0]['status'] ?? '') === 'running',
            'test'      => $test,
        ]);
    }

    /** POST /admin/powerbi */
    public function save(): void
    {
        $errors = $this->app->powerBiSettings()->save([
            'tenant_id'     => $this->request->input('tenant_id'),
            'client_id'     => $this->request->input('client_id'),
            'client_secret' => $this->request->input('client_secret'),
        ]);
        $errors
            ? array_map(static fn ($e) => Session::flash('danger', $e), $errors)
            : Session::flash('success', 'Impostazioni salvate. Ora premi «Prova collegamento».');
        $this->redirect(url('admin/powerbi'));
    }

    /** POST /admin/powerbi/prova */
    public function test(): void
    {
        $cfg = $this->app->powerBiSettings()->all();
        $this->show((new ConnectionTest($this->app->http()))->run($cfg));
    }

    /** POST /admin/powerbi/modalita — passa da dati di esempio a tenant reale (o viceversa). */
    public function switchMode(): void
    {
        $settings = $this->app->powerBiSettings();
        $target = $this->request->input('mode') === 'live' ? 'live' : 'fixture';

        if ($settings->source('mode') === 'config') {
            Session::flash('danger', 'La modalità è fissata in config/config.php: cambiala lì.');
        } elseif ($target === 'live' && !$settings->isComplete()) {
            Session::flash('danger', 'Prima compila tenant ID, client ID e segreto e prova il collegamento.');
        } elseif ($this->request->input('confirm') !== '1') {
            Session::flash('danger', 'Spunta la conferma: il catalogo attuale verrà cancellato.');
        } else {
            $this->app->repo(MaintenanceRepository::class)->purgeCatalog();
            $settings->setMode($target);
            $this->startSync('full');
            Session::flash('success', $target === 'live'
                ? 'Dati di esempio cancellati. Prima sincronizzazione con il tenant reale avviata: può richiedere qualche minuto.'
                : 'Tornato ai dati di esempio: sincronizzazione avviata.');
        }
        $this->redirect(url('admin/powerbi'));
    }

    /** POST /admin/powerbi/sync */
    public function sync(): void
    {
        $this->startSync($this->request->input('mode') === 'full' ? 'full' : 'incremental');
        Session::flash('success', 'Sincronizzazione avviata. La pagina si aggiorna da sola finché non termina.');
        $this->redirect(url('admin/powerbi'));
    }

    private function startSync(string $mode): void
    {
        try {
            $this->app->backgroundSync()->start($mode);
            usleep(800_000); // dà tempo al processo di registrare la sincronizzazione "in corso"
        } catch (\Throwable $e) {
            Session::flash('danger', 'Impossibile avviare la sincronizzazione: ' . $e->getMessage());
        }
    }
}
