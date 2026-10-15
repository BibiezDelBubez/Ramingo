<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Session;
use Ramingo\Models\SynonymRepository;
use Ramingo\Services\Search\QueryInterpreter;

/** Sinonimi di ricerca: gruppi di parole che la ricerca tratta come uguali. */
final class SynonymController extends BaseController
{
    public function before(): void
    {
        parent::before();
        $this->requireAdmin();
    }

    /** GET /admin/sinonimi?prova=HR&nuovo=parola */
    public function index(): void
    {
        $file   = require RAMINGO_ROOT . '/config/synonyms.php';
        $groups = $this->app->repo(SynonymRepository::class)->all($file['groups'] ?? []);
        $test   = mb_substr((string) $this->request->query('prova', ''), 0, 200);

        $this->render('admin/synonyms', [
            'title'   => 'Sinonimi di ricerca',
            'groups'  => $groups,
            'new'     => mb_substr((string) $this->request->query('nuovo', ''), 0, 60),
            'test'    => $test,
            'concepts' => $test !== '' ? (new QueryInterpreter($this->app->vocabulary()))->interpret($test) : [],
        ]);
    }

    /** POST /admin/sinonimi — salva tutti i gruppi (righe vuote = eliminate) */
    public function save(): void
    {
        $groups = [];
        $single = 0;
        foreach ((array) $this->request->input('groups', []) as $text) {
            $words = SynonymRepository::split((string) $text);
            if (count($words) >= 2) {
                $groups[] = $words;
            } elseif ($words) {
                $single++; // una parola sola non è un gruppo di sinonimi
            }
        }
        $this->app->repo(SynonymRepository::class)->replaceAll(array_slice($groups, 0, 500), $this->user->displayIdentity());
        Session::flash('success', 'Sinonimi salvati: ' . count($groups) . ' gruppi.'
            . ($single ? " $single righe con una sola parola sono state ignorate: servono almeno due parole." : ''));
        $this->redirect(url('admin/sinonimi'));
    }
}
