<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Domain\Source;
use Ramingo\Domain\Sourced;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\AreaRepository;
use Ramingo\Models\ArtifactUserRepository;
use Ramingo\Models\DatasetRepository;
use Ramingo\Models\GlossaryRepository;
use Ramingo\Models\ReportRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Ai\AiAssistant;
use Ramingo\Services\Catalog\CardPresenter;
use Ramingo\Services\Catalog\ReportUsage;

final class ReportController extends BaseController
{
    /** GET /report/{id} — scheda: in alto per chi usa il report, in fondo i dettagli tecnici. */
    public function show(string $id): void
    {
        $reports = $this->app->repo(ReportRepository::class);
        $report  = $reports->find($id) ?? $this->abort(404, 'Report non trovato nel catalogo.');

        $access   = $this->app->access()->states($this->user, [['entity_type' => 'report'] + $report])['report:' . $id];
        $annos    = $this->app->repo(AnnotationRepository::class);
        $notes    = $annos->currentFor('report', [$id])[$id] ?? [];
        $owners   = $this->app->repo(ArtifactUserRepository::class)->owners('report', $id);
        $datasets = $this->app->repo(DatasetRepository::class);
        $dsId     = $report['dataset_id'];
        $schema   = $dsId ? $datasets->schema($dsId) : [];

        $measureNotes = [];
        if ($dsId) {
            $ids = [];
            foreach ($schema as $t) {
                foreach ($t['measures'] as $m) {
                    $ids[] = AiAssistant::measureId($dsId, $m['name']);
                }
            }
            $measureNotes = $annos->currentFor('measure', $ids);
        }

        $description = CardPresenter::bestDescription($report['description'], $notes);
        $visuals     = $reports->visualsByPage($id);
        $usage       = ReportUsage::fromVisuals($visuals);

        $this->render('report/show', [
            'title'        => $report['name'],
            'r'            => $report,
            'access'       => $access,
            'freshness'    => \Ramingo\Services\Catalog\Freshness::of($report, (int) $this->app->config()->get('catalog.stale_days', 7)),
            'notes'        => $notes,
            'description'  => $description,
            'tags'         => $this->app->repo(TagRepository::class)->forEntities('report', [$id])[$id] ?? [],
            'tagsInUse'    => $this->app->repo(TagRepository::class)->inUse(),
            'owners'       => $owners,
            'areaPath'     => $report['area_id'] ? ($this->app->repo(AreaRepository::class)->paths()[(int) $report['area_id']] ?? null) : null,
            'pages'        => $reports->pages($id),
            'visuals'      => $visuals,
            'usage'        => $usage,
            'schema'       => $schema,
            'humanData'    => $this->humanData($schema, $usage),
            'glossary'     => $this->app->repo(GlossaryRepository::class),
            'datasources'  => $dsId ? $datasets->datasources($dsId) : [],
            'siblings'     => $dsId ? $reports->siblingsByDataset($dsId, $id) : [],
            'showExpr'     => $this->canSeeExpressions($access),
            'canEdit'      => $this->canEdit($owners),
            'history'      => $this->user->isAdmin() ? $annos->history('report', $id, 15) : [],
            'urls'         => $this->app->urls(),
            'measureNotes' => $measureNotes,
            'ai'           => $this->app->aiAssistant()->describeConnection($this->user),
            'techOpen'     => $this->request->query('tecnico') === '1',
        ]);
    }

    /** POST /report/{id}/definizione — legge pagine e campi usati da un .pbix caricato a mano. */
    public function uploadDefinition(string $id): void
    {
        $reports = $this->app->repo(ReportRepository::class);
        if (!$reports->exists($id)) {
            $this->abort(404, 'Report non trovato.');
        }
        if (!$this->canEdit($this->app->repo(ArtifactUserRepository::class)->owners('report', $id))) {
            $this->abort(403, 'Solo amministratori di Ramingo e proprietari del report possono farlo.');
        }
        $file = $this->request->file('pbix');
        $error = match (true) {
            $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE => 'Nessun file selezionato.',
            in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) =>
                'File troppo grande per le impostazioni di PHP (upload_max_filesize / post_max_size in php.ini).',
            $file['error'] !== UPLOAD_ERR_OK => 'Caricamento non riuscito.',
            !str_ends_with(strtolower((string) $file['name']), '.pbix') => 'Serve un file .pbix.',
            default => null,
        };
        if ($error === null) {
            try {
                $def = (new \Ramingo\Services\Definition\ReportDefinitionParser())->parsePbix($file['tmp_name']);
                $reports->saveDefinition($id, $def);
                $reports->touchDefinitionFetched($id, now_iso());
                $this->app->insights()->refreshReport($id);
                $this->app->indexer()->indexReport($id);
                \Ramingo\Core\Session::flash('success', sprintf('Struttura letta: %d pagine, %d grafici.', count($def['pages']), count($def['visuals'])));
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            } finally {
                @unlink($file['tmp_name']);
            }
        }
        if ($error !== null) {
            \Ramingo\Core\Session::flash('danger', $error);
        }
        $this->redirect(url('report/' . $id, ['tecnico' => 1]) . '#tecnico');
    }

    /**
     * Tabelle e misure spiegate in linguaggio umano, per i colleghi. Compare solo ciò che ha un significato
     * scritto da una persona: glossario (manuale) o descrizione in Power BI. Le spiegazioni AI stanno nei dettagli tecnici.
     * @return array{tables: array, measures: array}
     */
    private function humanData(array $schema, ReportUsage $usage): array
    {
        $glossary = $this->app->repo(GlossaryRepository::class);
        $out = ['tables' => [], 'measures' => []];
        foreach ($schema as $t) {
            if ($t['is_hidden']) {
                continue;
            }
            // con un modello condiviso si mostrano solo tabelle e misure che QUESTO report usa
            $tableUsed = $usage->usesTable($t['name']);
            $g = $glossary->find('table', $t['name']);
            $desc = Sourced::firstFilled(
                $g && $g['description'] ? new Sourced($g['description'], Source::Manual, $g['updated_by'], $g['updated_at']) : null,
                $t['description'] ? Sourced::pbi($t['description']) : null,
            );
            if ($tableUsed && (($g['label'] ?? null) || $desc)) {
                $out['tables'][] = ['label' => ($g['label'] ?? null) ?: $t['name'], 'tech' => $t['name'], 'description' => $desc];
            }
            foreach ($t['measures'] as $m) {
                if ($m['is_hidden'] || !$usage->usesMeasure($m['name'])) {
                    continue;
                }
                $gm = $glossary->find('measure', $m['name']);
                $mdesc = Sourced::firstFilled(
                    $gm && $gm['description'] ? new Sourced($gm['description'], Source::Manual, $gm['updated_by'], $gm['updated_at']) : null,
                    $m['description'] ? Sourced::pbi($m['description']) : null,
                );
                if (($gm['label'] ?? null) || $mdesc) {
                    $out['measures'][] = ['label' => ($gm['label'] ?? null) ?: $m['name'], 'tech' => $m['name'], 'description' => $mdesc];
                }
            }
        }
        return $out;
    }
}
