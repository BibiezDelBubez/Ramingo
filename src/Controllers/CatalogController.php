<?php
declare(strict_types=1);

namespace Ramingo\Controllers;


final class CatalogController extends BaseController
{
    /** GET /  — pagina iniziale: ricerca in alto, colonna filtri a sinistra, elenco al centro. */
    public function index(): void
    {
        $this->render('catalog/index', $this->catalogPage('', ''));
    }

    /** GET /cerca?q=…  — stessa pagina, con i risultati della ricerca al posto dell'elenco completo. */
    public function search(): void
    {
        $q = mb_substr((string) $this->request->query('q', ''), 0, 300);
        $this->render('catalog/index', $this->catalogPage($q, $q !== '' ? 'cerca' : ''));
    }

    /** GET /fonti — spiega le quattro provenienze dei dati. */
    public function sources(): void
    {
        $this->render('catalog/sources', ['title' => 'Da dove arrivano le informazioni']);
    }
}
