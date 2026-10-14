<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

use Ramingo\Core\Config;
use Ramingo\Core\Session;
use Ramingo\Models\UserAiSettingRepository;
use Ramingo\Services\Auth\CurrentUser;
use Ramingo\Services\Http\HttpClient;

/**
 * Decide QUALE collegamento AI usare per l'utente corrente:
 *   1. la sua chiave personale, se l'ha impostata (e se ai.allow_user_keys = true)
 *   2. altrimenti la chiave aziendale (config ai.company)
 *   3. altrimenti nessuna: le funzioni AI non vengono mostrate.
 *
 * Dove vive la chiave personale:
 *   - con login Entra  -> database, CIFRATA con SecretBox
 *   - senza login      -> solo nella sessione del browser (sparisce alla chiusura): senza
 *                         identità non si può legare una chiave a una persona.
 */
final class AiConnections
{
    public function __construct(
        private readonly Config $config,
        private readonly UserAiSettingRepository $repo,
        private readonly SecretBox $box,
        private readonly HttpClient $http,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('ai.enabled', false);
    }

    public function allowPersonal(): bool
    {
        return $this->enabled() && (bool) $this->config->get('ai.allow_user_keys', true);
    }

    /** @return array{0: AiConfig, 1: string}|null  [configurazione, 'personal'|'company'] */
    public function resolve(CurrentUser $user): ?array
    {
        if (!$this->enabled()) {
            return null;
        }
        if ($this->allowPersonal() && ($personal = $this->personal($user))) {
            return [$personal, 'personal'];
        }
        $company = $this->company();
        return $company ? [$company, 'company'] : null;
    }

    public function company(): ?AiConfig
    {
        $c = (array) $this->config->get('ai.company', []);
        if (empty($c['provider'])) {
            return null;
        }
        try {
            return AiConfig::fromArray($c, strict: false);
        } catch (AiException) {
            return null; // configurazione incompleta: segnalata in Amministrazione
        }
    }

    public function companyError(): ?string
    {
        $c = (array) $this->config->get('ai.company', []);
        if (empty($c['provider'])) {
            return 'Nessuna chiave aziendale configurata (ai.company in config.php).';
        }
        try {
            AiConfig::fromArray($c, strict: false);
            return null;
        } catch (AiException $e) {
            return $e->getMessage();
        }
    }

    public function personal(CurrentUser $user): ?AiConfig
    {
        if ($user->oid) {
            $row = $this->repo->find($user->oid);
            if (!$row) {
                return null;
            }
            return AiConfig::fromArray([
                'provider' => $row['provider'], 'model' => $row['model'], 'endpoint' => $row['endpoint'],
                'api_key'  => $row['api_key_enc'] ? $this->box->decrypt($row['api_key_enc']) : '',
            ], strict: true);
        }
        $s = Session::get('ai_personal');
        return is_array($s) ? AiConfig::fromArray($s, strict: true) : null;
    }

    /** Dati mostrabili (MAI la chiave): fornitore, modello, ultime cifre, dove è conservata. */
    public function personalSummary(CurrentUser $user): ?array
    {
        if ($user->oid) {
            $row = $this->repo->find($user->oid);
            return $row ? ['provider' => $row['provider'], 'model' => $row['model'], 'endpoint' => $row['endpoint'],
                           'hint' => $row['key_hint'], 'storage' => 'database (cifrata)'] : null;
        }
        $s = Session::get('ai_personal');
        return is_array($s) ? ['provider' => $s['provider'], 'model' => $s['model'], 'endpoint' => $s['endpoint'],
                               'hint' => self::hint($s['api_key']), 'storage' => 'solo questa sessione del browser'] : null;
    }

    /** Salva la chiave personale. Se la chiave è lasciata vuota, si conserva quella già salvata. */
    public function savePersonal(CurrentUser $user, array $input): AiConfig
    {
        if (!$this->allowPersonal()) {
            throw new AiException('Le chiavi personali sono disattivate dall\'amministratore.');
        }
        if (trim((string) ($input['api_key'] ?? '')) === '') {
            $current = $this->personal($user);
            if ($current && $current->provider === ($input['provider'] ?? '')) {
                $input['api_key'] = $current->apiKey;
            }
        }
        $cfg = AiConfig::fromArray($input, strict: true);

        if ($user->oid) {
            $this->repo->save($user->oid, $cfg->provider, $cfg->model, $cfg->endpoint,
                $cfg->apiKey !== '' ? $this->box->encrypt($cfg->apiKey) : '', self::hint($cfg->apiKey));
        } else {
            Session::set('ai_personal', ['provider' => $cfg->provider, 'model' => $cfg->model,
                'endpoint' => $cfg->endpoint, 'api_key' => $cfg->apiKey]);
        }
        return $cfg;
    }

    public function deletePersonal(CurrentUser $user): void
    {
        $user->oid ? $this->repo->delete($user->oid) : Session::pull('ai_personal');
    }

    public function provider(AiConfig $cfg): AiProvider
    {
        return $cfg->provider === 'anthropic'
            ? new AnthropicProvider($this->http, $cfg)
            : new OpenAiCompatibleProvider($this->http, $cfg);
    }

    private static function hint(string $key): string
    {
        return $key === '' ? '(nessuna)' : '…' . substr($key, -4);
    }
}
