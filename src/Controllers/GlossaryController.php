<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\GlossaryRepository;

/** Glossario: dare un nome comprensibile ai nomi tecnici di tabelle, misure e colonne. */
final class GlossaryController extends BaseController
{
    private const PAGE_SIZE = 100;

    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/glossario?kind=table&q=...&missing=1&page=1 */
    public function index(): void
    {
        $kind    = array_key_exists((string) $this->request->query('kind'), GlossaryRepository::KINDS) ? (string) $this->request->query('kind') : 'table';
        $search  = mb_substr((string) $this->request->query('q', ''), 0, 100);
        $missing = $this->request->query('missing') === '1';
        $page    = max(1, (int) $this->request->query('page', 1));
        $repo    = $this->app->repo(GlossaryRepository::class);
        $result  = $repo->terms($kind, $search, $missing, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $this->render('admin/glossary', [
            'title'   => 'Glossario',
            'kind'    => $kind,
            'search'  => $search,
            'missing' => $missing,
            'page'    => $page,
            'pages'   => (int) ceil($result['total'] / self::PAGE_SIZE),
            'total'   => $result['total'],
            'rows'    => $result['rows'],
            'counts'  => $repo->missingCounts(),
            'scripts' => ['glossary.js'],
        ]);
    }

    /** POST /admin/glossario — salva solo le righe modificate */
    public function save(): void
    {
        $kind = (string) $this->request->input('kind');
        if (!array_key_exists($kind, GlossaryRepository::KINDS)) {
            $this->abort(400, 'Tipo non valido.');
        }
        $repo = $this->app->repo(GlossaryRepository::class);
        $changed = 0;
        foreach ((array) $this->request->input('rows', []) as $row) {
            if (!is_array($row) || !isset($row['term'])) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $desc  = trim((string) ($row['description'] ?? ''));
            if ($label === trim((string) ($row['orig_label'] ?? '')) && $desc === trim((string) ($row['orig_description'] ?? ''))) {
                continue;
            }
            $repo->save($kind, (string) $row['term'], $label, $desc, $this->user->displayIdentity());
            $changed++;
        }
        if ($changed) {
            $this->propagate();
        }
        Session::flash('success', $changed ? "Glossario aggiornato ($changed voci)." : 'Nessuna modifica.');
        $this->back();
    }

    /** POST /admin/glossario/voce — salva una sola voce (salvataggio automatico della pagina), risponde in JSON */
    public function saveOne(): void
    {
        $kind = (string) $this->request->input('kind');
        $term = trim((string) $this->request->input('term', ''));
        if (!array_key_exists($kind, GlossaryRepository::KINDS) || $term === '') {
            $this->json(['ok' => false, 'message' => 'Voce non valida.'], 422);
        }
        $this->app->repo(GlossaryRepository::class)->save($kind, $term,
            (string) $this->request->input('label', ''), (string) $this->request->input('description', ''), $this->user->displayIdentity());
        $this->propagate();
        $this->json(['ok' => true]);
    }

    /** I nomi comprensibili entrano nelle sintesi dei report e nella ricerca. */
    private function propagate(): void
    {
        $this->app->insights()->refreshAll();
        $this->app->indexer()->rebuildAll();
    }
}
