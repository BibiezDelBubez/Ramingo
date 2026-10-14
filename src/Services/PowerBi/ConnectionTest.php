<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

use Ramingo\Services\Http\HttpClient;
use Ramingo\Services\Http\HttpException;

/**
 * Verifica passo-passo del collegamento, con messaggi comprensibili.
 * Passo 1: Microsoft rilascia il token? (tenant, client id, segreto)
 * Passo 2: Power BI autorizza la lettura dell'elenco dei workspace? (impostazioni tenant)
 */
final class ConnectionTest
{
    /** Codici di errore Microsoft più frequenti → spiegazione. */
    private const AADSTS = [
        '7000215' => 'Il segreto non è corretto. Copia la colonna «Valore» (non «ID segreto») da Certificati e segreti.',
        '7000222' => 'Il segreto è scaduto. Creane uno nuovo in Certificati e segreti.',
        '700016'  => 'L\'ID applicazione non esiste in questo tenant. Controlla client ID e tenant ID.',
        '90002'   => 'Il tenant ID non esiste. Controlla l\'«ID directory (tenant)».',
        '900023'  => 'Il tenant ID non è valido.',
    ];

    public function __construct(private readonly HttpClient $http)
    {
    }

    /** @return array<int, array{step:string, ok:bool, message:string}> */
    public function run(array $cfg): array
    {
        $steps = [];

        // Passo 1: token
        $tokens = new TokenProvider($this->http, $cfg);
        try {
            $claims = self::claims($tokens->token());
            $steps[] = ['step' => 'Accesso a Microsoft (tenant, client ID, segreto)', 'ok' => true, 'message' => 'Credenziali corrette.'];
            $steps[] = $this->checkTokenPermissions($claims);
        } catch (HttpException $e) {
            $steps[] = ['step' => 'Accesso a Microsoft (tenant, client ID, segreto)', 'ok' => false, 'message' => $this->explainToken($e->getMessage())];
            return $steps;
        }

        // Passo 2: API amministrative di Power BI
        try {
            $ids = (new RestPowerBiClient($this->http, $tokens, $cfg))->modifiedWorkspaceIds(null, true);
            $steps[] = ['step' => 'Permesso di lettura in Power BI', 'ok' => true,
                'message' => sprintf('Power BI risponde: %d workspace visibili. Puoi passare ai dati reali.', count($ids))];
        } catch (HttpException $e) {
            $steps[] = ['step' => 'Permesso di lettura in Power BI', 'ok' => false, 'message' => match (true) {
                in_array($e->status, [401, 403], true) =>
                    'Risposta di Power BI: ' . $e->getMessage() . '. '
                    . 'Le credenziali sono giuste, ma Power BI non autorizza ancora l\'app. Nel Portale di amministrazione di Power BI '
                    . '(Impostazioni tenant) attiva per il gruppo SG-Ramingo l\'impostazione sulle API di amministrazione di sola lettura per le entità servizio (in inglese «Service principals can access read-only admin APIs»). '
                    . 'Verifica anche che l\'app sia nel gruppo e che NON abbia autorizzazioni API Power BI. Le modifiche possono richiedere fino a 15 minuti.',
                default => 'Errore inatteso: ' . $e->getMessage(),
            }];
        }
        return $steps;
    }

    /**
     * Il token dice quali permessi "di tipo Applicazione" ha l'app (claim "roles").
     * Per le API admin di sola lettura NON deve averne nessuno su Power BI: è la causa più comune del rifiuto.
     */
    private function checkTokenPermissions(array $c): array
    {
        $roles = array_values((array) ($c['roles'] ?? []));
        $who = sprintf('App %s nel tenant %s.', $c['appid'] ?? $c['azp'] ?? '?', $c['tid'] ?? '?');
        if ($roles) {
            return ['step' => 'Permessi dell\'app in Azure', 'ok' => false, 'message' => $who
                . ' L\'app ha permessi Power BI di tipo «Applicazione»: ' . implode(', ', $roles) . '. '
                . 'Con questi permessi Power BI rifiuta le API di amministrazione. In Azure apri Ramingo-Sync → Autorizzazioni API, '
                . 'rimuovi le autorizzazioni «Power BI Service», poi premi «Concedi consenso amministratore» per confermare la rimozione.'];
        }
        return ['step' => 'Permessi dell\'app in Azure', 'ok' => true, 'message' => $who . ' Nessun permesso Power BI di tipo Applicazione: corretto.'];
    }

    /** Legge il contenuto (non la firma) del token JWT. Il token non viene mai mostrato né salvato. */
    private static function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        $json = isset($parts[1]) ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    private function explainToken(string $raw): string
    {
        foreach (self::AADSTS as $code => $text) {
            if (str_contains($raw, 'AADSTS' . $code)) {
                return $text;
            }
        }
        if (str_contains($raw, 'Configurazione Power BI incompleta')) {
            return 'Mancano dei valori: compila tenant ID, client ID e segreto.';
        }
        if (str_contains($raw, 'Errore di rete') || str_contains($raw, 'SSL')) {
            return 'Il PC non raggiunge login.microsoftonline.com: controlla connessione, proxy o certificati (http.proxy / http.ca_bundle).';
        }
        return 'Microsoft ha rifiutato l\'accesso: ' . mb_substr($raw, 0, 250);
    }
}
