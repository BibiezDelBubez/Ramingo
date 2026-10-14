<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Domain\Source;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\ArtifactUserRepository;
use Ramingo\Models\DashboardRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\TagRepository;

/** Salvataggio dei testi scritti a mano (Source::Manual) per report e dashboard. */
final class AnnotationController extends BaseController
{
    /** POST /annotazioni/{type}/{id} */
    public function save(string $type, string $id): void
    {
        $this->guard($type, $id);
        $repo = $this->app->repo(AnnotationRepository::class);

        $author = $this->user->displayIdentity();
        $changed = 0;
        foreach (self::editableFields() as $field => $_label) {
            $value = mb_substr((string) $this->request->input($field, ''), 0, 4000);
            $changed += $repo->save($type, $id, $field, $value, Source::Manual, $author) ? 1 : 0;
        }
        $tags = array_slice(explode(',', mb_substr((string) $this->request->input('tags', ''), 0, 500)), 0, 20);
        $this->app->repo(TagRepository::class)->replace($type, $id, $tags, Source::Manual);

        $type === 'report' ? $this->app->indexer()->indexReport($id) : $this->app->indexer()->indexDashboard($id);

        Session::flash('success', $changed ? 'Descrizioni salvate.' : 'Nessuna modifica da salvare.');
        $this->redirect(url("$type/$id") . '#descrizioni');
    }

    /** L'elemento esiste e l'utente può modificarne le descrizioni? */
    private function guard(string $type, string $id): void
    {
        $exists = match ($type) {
            'report'    => $this->app->repo(ReportRepository::class)->exists($id),
            'dashboard' => $this->app->repo(DashboardRepository::class)->exists($id),
            default     => false,
        };
        if (!$exists) {
            $this->abort(404, 'Elemento non trovato.');
        }
        if (!$this->canEdit($this->app->repo(ArtifactUserRepository::class)->owners($type, $id))) {
            $this->abort(403, 'Solo amministratori di Ramingo e proprietari del contenuto possono modificare le descrizioni.');
        }
    }

    /** Proposta automatica → campo ufficiale in cui viene copiata quando qualcuno la approva. */
    public const APPROVE_TO = [
        'ai_description' => 'description',
        'summary'        => 'description',
        'ai_audience'    => 'audience',
        'kpi'            => 'kpi',
    ];

    /** POST /annotazioni/{type}/{id}/approva  (field = campo automatico da approvare) */
    public function approve(string $type, string $id): void
    {
        $this->guard($type, $id);
        $field = (string) $this->request->input('field');
        $target = self::APPROVE_TO[$field] ?? $this->abort(400, 'Campo non approvabile.');
        $repo = $this->app->repo(AnnotationRepository::class);
        $auto = $repo->currentFor($type, [$id])[$id][$field]['auto'] ?? $this->abort(404, 'Proposta non trovata.');
        $repo->save($type, $id, $target, (string) $auto->value, Source::Manual, $this->user->displayIdentity() . ' (approvato da proposta automatica)');
        $type === 'report' ? $this->app->indexer()->indexReport($id) : $this->app->indexer()->indexDashboard($id);
        Session::flash('success', 'Proposta approvata: ora è visibile ai colleghi come testo ufficiale. Puoi sempre correggerla.');
        $this->redirect(url("$type/$id") . '#descrizioni');
    }

    /** I campi modificabili a mano: tutti tranne quelli generati automaticamente (sintesi e proposte AI). */
    public static function editableFields(): array
    {
        return array_diff_key(AnnotationRepository::FIELDS, ['summary' => true, 'ai_description' => true, 'ai_audience' => true]);
    }
}
