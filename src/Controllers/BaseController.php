<?php
declare(strict_types=1);

namespace Ramingo\Controllers;

use Ramingo\Core\Controller;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\TagRepository;
use Ramingo\Services\Auth\AccessService;
use Ramingo\Services\Catalog\CardPresenter;

/** Helper condivisi dai controller dell'applicazione. */
abstract class BaseController extends Controller
{
    protected function cards(): CardPresenter
    {
        return new CardPresenter(
            $this->app->repo(AnnotationRepository::class),
            $this->app->repo(TagRepository::class),
            $this->app->access(),
            (int) $this->app->config()->get('catalog.stale_days', 7),
        );
    }

    /**
     * Ricerca + schede pronte per le viste (usata dalla ricerca e dall'AI).
     * @return array{q:string, concepts:array, mode:string, cards:array}
     */
    protected function searchCards(string $q, int $limit): array
    {
        if (mb_strlen($q) < 2) {
            return ['q' => $q, 'concepts' => [], 'mode' => 'all', 'cards' => []];
        }
        $result = $this->app->search()->search($q, $limit * 2);
        $rows   = $this->app->repo(\Ramingo\Models\CatalogRepository::class)->byKeys(array_keys($result['hits']));
        $cards  = $this->visibleCards($this->cards()->present($rows, $this->user));
        foreach ($cards as &$c) {
            $c['why'] = $result['hits'][$c['key']]['why'] ?? [];
        }
        return ['q' => $q, 'concepts' => $result['concepts'], 'mode' => $result['mode'], 'cards' => array_slice($cards, 0, $limit)];
    }

    /** Identità per log e limiti AI: oid Entra, oppure IP se non c'è login. */
    protected function aiUserId(): string
    {
        return $this->user->oid ?? 'locale:' . $this->request->ip;
    }

    /** Applica l'opzione catalog.hide_inaccessible. */
    protected function visibleCards(array $cards): array
    {
        if (!$this->app->config()->get('catalog.hide_inaccessible', false) || $this->user->isAdmin()) {
            return $cards;
        }
        return array_values(array_filter($cards, static fn ($c) => $c['access'] !== AccessService::NO));
    }

    /** Può scrivere descrizioni? Amministratori di Ramingo e proprietari del contenuto in Power BI. */
    protected function canEdit(array $owners): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }
        $email = strtolower((string) $this->user->email);
        return $email !== '' && in_array($email, array_map('strtolower', array_filter(array_column($owners, 'email'))), true);
    }

    /** Può vedere DAX e Power Query? Regola configurabile (catalog.show_expressions). */
    protected function canSeeExpressions(string $accessState): bool
    {
        return match ($this->app->config()->get('catalog.show_expressions', 'accessible')) {
            'all'        => true,
            'admins'     => $this->user->isAdmin(),
            default      => $this->user->isAdmin() || $accessState === AccessService::YES
                || $this->app->config()->get('auth.mode') !== 'entra',
        };
    }
}
