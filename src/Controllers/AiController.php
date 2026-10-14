<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\CatalogRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Services\Ai\AiConfig;
use Ramingo\Services\Ai\AiException;

/** Funzioni AI e impostazione della chiave personale. */
final class AiController extends BaseController
{
    public function before(): void
    {
        parent::before();
        if (!$this->app->aiConnections()->enabled()) {
            $this->abort(404, 'Le funzioni AI non sono attive (ai.enabled in config.php).');
        }
    }

    /** POST /ai/report/{id}/spiega */
    public function explainReport(string $id): void
    {
        if (!$this->app->repo(ReportRepository::class)->exists($id)) {
            $this->abort(404, 'Report non trovato.');
        }
        try {
            $res = $this->app->aiAssistant()->explainReport($id, $this->user, $this->aiUserId());
            $this->app->indexer()->indexReport($id);
            Session::flash('success', $res['changed']
                ? 'Proposta generata (' . $res['generator'] . '). La trovi qui sotto in «Proposte automatiche»: se è corretta, approvala per renderla visibile ai colleghi.'
                : 'L\'AI non ha prodotto novità rispetto alla spiegazione già presente.');
        } catch (AiException $e) {
            Session::flash('danger', $e->getMessage());
        }
        $this->redirect(url('report/' . $id, ['tecnico' => 1]) . '#tecnico');
    }

    /** POST /ai/chiedi — domanda libera sul catalogo. */
    public function ask(): void
    {
        $q = mb_substr((string) $this->request->input('q', ''), 0, 500);
        $data = $this->searchCards($q, 25);
        $candidates = $data['cards'];
        if (!$candidates) { // nessun risultato con le parole: si offrono all'AI i primi elementi del catalogo
            $rows = $this->app->repo(CatalogRepository::class)->find([], 40)['items'];
            $candidates = $this->visibleCards($this->cards()->present($rows, $this->user));
        }
        $aiAnswer = null;
        $aiError = null;
        try {
            $aiAnswer = $q !== '' ? $this->app->aiAssistant()->askCatalog($q, $candidates, $this->user, $this->aiUserId()) : null;
        } catch (AiException $e) {
            $aiError = $e->getMessage();
        }
        $this->render('search/index', $data + [
            'title'    => "Ricerca: $q",
            'ai'       => $this->app->aiAssistant()->describeConnection($this->user),
            'aiAnswer' => $aiAnswer,
            'aiError'  => $aiError,
        ]);
    }

    /** GET /ai/impostazioni */
    public function settings(): void
    {
        $conn = $this->app->aiConnections();
        $personalError = null;
        try {
            $conn->personal($this->user);
        } catch (AiException $e) {
            $personalError = $e->getMessage();
        }
        $this->render('ai/settings', [
            'title'         => 'La mia AI',
            'providers'     => AiConfig::PROVIDERS,
            'personal'      => $conn->personalSummary($this->user),
            'personalError' => $personalError,
            'allowPersonal' => $conn->allowPersonal(),
            'company'       => $conn->company()?->label(),
            'active'        => $this->app->aiAssistant()->describeConnection($this->user),
            'sendDax'       => (bool) $this->app->config()->get('ai.send_expressions', false),
        ]);
    }

    /** POST /ai/impostazioni */
    public function save(): void
    {
        try {
            $cfg = $this->app->aiConnections()->savePersonal($this->user, [
                'provider' => (string) $this->request->input('provider', ''),
                'model'    => (string) $this->request->input('model', ''),
                'endpoint' => (string) $this->request->input('endpoint', ''),
                'api_key'  => (string) $this->request->input('api_key', ''),
            ]);
            Session::flash('success', 'Collegamento personale salvato: ' . $cfg->label() . '. Usa «Prova» per verificarlo.');
        } catch (AiException $e) {
            Session::flash('danger', $e->getMessage());
        }
        $this->redirect(url('ai/impostazioni'));
    }

    /** POST /ai/impostazioni/elimina */
    public function delete(): void
    {
        $this->app->aiConnections()->deletePersonal($this->user);
        Session::flash('success', 'Chiave personale eliminata. Verrà usata quella aziendale, se configurata.');
        $this->redirect(url('ai/impostazioni'));
    }

    /** POST /ai/prova */
    public function test(): void
    {
        try {
            Session::flash('success', 'Collegamento riuscito: ' . $this->app->aiAssistant()->test($this->user, $this->aiUserId()));
        } catch (AiException $e) {
            Session::flash('danger', 'Collegamento non riuscito: ' . $e->getMessage());
        }
        $this->back();
    }
}
