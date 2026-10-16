<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\AreaRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Catalog\ManualTexts;

/** Amministrazione → Esporta e importa i testi scritti a mano (file per Excel). */
final class TextsController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    private function texts(): ManualTexts
    {
        return new ManualTexts($this->app->db(), $this->app->repo(AnnotationRepository::class),
            $this->app->repo(TagRepository::class), $this->app->repo(AreaRepository::class));
    }

    /** GET /admin/testi */
    public function index(): void
    {
        $this->render('admin/texts', [
            'title'   => 'Esporta e importa i testi',
            'headers' => ManualTexts::headers(),
            'result'  => Session::pull('texts_import'),
        ]);
    }

    /** GET /admin/testi/esporta — file CSV per Excel */
    public function export(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ramingo-testi-' . date('Y-m-d') . '.csv"');
        echo $this->texts()->exportCsv();
        exit;
    }

    /** POST /admin/testi/importa */
    public function import(): void
    {
        $file = $this->request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('danger', 'Nessun file caricato.');
            $this->redirect(url('admin/testi'));
        }
        $content = (string) file_get_contents($file['tmp_name']);
        if (strlen($content) > 5_000_000) {
            Session::flash('danger', 'File troppo grande.');
            $this->redirect(url('admin/testi'));
        }
        $result = $this->texts()->import($content, $this->user->displayIdentity());
        foreach ($result['touched'] as [$type, $id]) {
            $type === 'report' ? $this->app->indexer()->indexReport($id) : $this->app->indexer()->indexDashboard($id);
        }
        unset($result['touched']);
        Session::set('texts_import', $result);
        $this->redirect(url('admin/testi'));
    }
}
