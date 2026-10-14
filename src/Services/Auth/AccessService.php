<?php
declare(strict_types=1);

namespace Ramingo\Services\Auth;

use Ramingo\Models\ArtifactUserRepository;

/**
 * Stima se l'utente corrente può aprire un report/dashboard, usando i permessi
 * letti da Power BI (utenti e gruppi) e i gruppi dell'utente (claim "groups").
 *
 * È una STIMA (interpretazione automatica): non vede link di condivisione
 * "chiunque nell'organizzazione", audience delle app o gruppi annidati oltre
 * il limite del token. L'autorità resta Power BI, che applica i permessi
 * veri quando l'utente apre il link.
 */
final class AccessService
{
    public const YES = 'yes';
    public const NO = 'no';
    public const UNKNOWN = 'unknown';

    public const LABELS = [
        self::YES     => ['Hai accesso', 'fa-solid fa-unlock', 'access-yes'],
        self::NO      => ['Probabilmente senza accesso', 'fa-solid fa-lock', 'access-no'],
        self::UNKNOWN => ['Accesso da verificare', 'fa-solid fa-circle-question', 'access-unknown'],
    ];

    public function __construct(private readonly ArtifactUserRepository $users)
    {
    }

    /**
     * @param array<int, array{entity_type:string, id:string, workspace_id:string, app_url?:?string}> $items
     * @return array<string, string> "tipo:id" => yes|no|unknown
     */
    public function states(CurrentUser $user, array $items): array
    {
        $principals = $user->principalIds();
        $granted = $this->users->grantedAmong(
            array_map(static fn ($i) => ['type' => $i['entity_type'], 'id' => $i['id'], 'workspace_id' => $i['workspace_id']], $items),
            $principals
        );
        $out = [];
        foreach ($items as $i) {
            $key = $i['entity_type'] . ':' . $i['id'];
            $out[$key] = match (true) {
                isset($granted[$key])                       => self::YES,
                !$principals || !$user->groupsComplete      => self::UNKNOWN,
                !empty($i['app_url'])                       => self::UNKNOWN, // audience dell'app non nota
                default                                     => self::NO,
            };
        }
        return $out;
    }
}
