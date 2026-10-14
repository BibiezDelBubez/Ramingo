<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\AreaRepository;
use Ramingo\Models\CatalogRepository;
use Ramingo\Models\WorkspaceRepository;

/**
 * Gestione delle categorie: albero, rinomina, icone, spostamenti, eliminazione,
 * assegnazione di workspace e singoli report. Ogni azione funziona sia come modulo
 * classico (redirect) sia via JavaScript (risposta JSON) per il trascinamento.
 */
final class CategoryController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/categorie */
    public function index(): void
    {
        $areas = $this->app->repo(AreaRepository::class);
        $items = [];
        foreach ($this->app->repo(CatalogRepository::class)->itemsWithOwnArea() as $it) {
            $items[$it['workspace_id']][] = $it;
        }
        $workspaces = $this->app->repo(WorkspaceRepository::class)->allWithAreas();
        $wsArea = array_column($workspaces, 'area_id', 'id');
        $unclassified = 0;
        foreach ($items as $wsId => $list) {
            foreach ($list as $it) {
                $unclassified += ($it['own_area_id'] === null && empty($wsArea[$wsId])) ? 1 : 0;
            }
        }
        $this->render('admin/categories', [
            'title'      => 'Categorie',
            'unclassifiedTotal' => $unclassified,
            'tree'       => $areas->tree(),
            'flat'       => $areas->flat(),
            'paths'      => $areas->paths(),
            'counts'     => $areas->itemCounts(),
            'icons'      => AreaRepository::ICONS,
            'workspaces' => $workspaces,
            'items'      => $items,
            'scripts'    => ['categories.js'],
        ]);
    }

    /** POST /admin/categorie/crea */
    public function create(): void
    {
        $this->act(function (AreaRepository $areas): string {
            $name = $this->name();
            $areas->create($name, $this->optionalId('parent_id'), (string) $this->request->input('icon', ''));
            return "Categoria «{$name}» creata.";
        });
    }

    /** POST /admin/categorie/modifica — rinomina e/o cambia icona */
    public function update(): void
    {
        $this->act(function (AreaRepository $areas): string {
            $id = $this->requiredId('id');
            if ($this->request->input('name') !== null) {
                $areas->rename($id, $this->name());
            }
            if ($this->request->input('icon') !== null) {
                $areas->setIcon($id, (string) $this->request->input('icon'));
            }
            return 'Categoria aggiornata.';
        });
    }

    /** POST /admin/categorie/sposta — dentro un'altra categoria o al primo livello */
    public function move(): void
    {
        $this->act(function (AreaRepository $areas): string {
            $areas->move($this->requiredId('id'), $this->optionalId('parent_id'), $this->optionalId('before_id'));
            return 'Categoria spostata.';
        });
    }

    /** POST /admin/categorie/elimina */
    public function delete(): void
    {
        $this->act(function (AreaRepository $areas): string {
            $areas->delete($this->requiredId('id'), $this->optionalId('move_to'));
            return 'Categoria eliminata. Il suo contenuto è stato spostato.';
        });
    }

    /** POST /admin/categorie/assegna — workspace, report o dashboard in una categoria ('' = nessuna / come il workspace) */
    public function assign(): void
    {
        $this->act(function (AreaRepository $areas): string {
            $kind = (string) $this->request->input('kind');
            $id = (string) $this->request->input('id');
            $areaId = $this->optionalId('area_id');
            $by = $this->user->displayIdentity();
            match ($kind) {
                'workspace' => $areas->assignWorkspace($id, $areaId, $by),
                'report', 'dashboard' => $areas->assignItem($kind, $id, $areaId, $by),
                default => throw new \InvalidArgumentException('Elemento non valido.'),
            };
            return $areaId === null
                ? ($kind === 'workspace' ? 'Workspace da classificare.' : 'Ora segue la categoria del suo workspace.')
                : 'Categoria assegnata.';
        });
    }

    /** Esegue l'azione, ricostruisce l'indice di ricerca e risponde in JSON o con redirect. */
    private function act(callable $fn): never
    {
        try {
            $message = $fn($this->app->repo(AreaRepository::class));
            $this->app->indexer()->rebuildAll(); // i nomi delle categorie sono parole chiave di ricerca
            $ok = true;
        } catch (\InvalidArgumentException|\PDOException $e) {
            $ok = false;
            $message = $e instanceof \PDOException && str_contains($e->getMessage(), 'UNIQUE')
                ? 'Esiste già una categoria con questo nome.'
                : $e->getMessage();
        }
        if ($this->request->wantsJson()) {
            $this->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        Session::flash($ok ? 'success' : 'danger', $message);
        $this->redirect(url('admin/categorie'));
    }

    private function name(): string
    {
        $name = trim((string) $this->request->input('name', ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new \InvalidArgumentException('Il nome deve avere da 1 a 80 caratteri.');
        }
        return $name;
    }

    private function requiredId(string $key): int
    {
        return $this->optionalId($key) ?? throw new \InvalidArgumentException('Categoria non indicata.');
    }

    private function optionalId(string $key): ?int
    {
        $v = (string) $this->request->input($key, '');
        return ctype_digit($v) ? (int) $v : null;
    }
}
