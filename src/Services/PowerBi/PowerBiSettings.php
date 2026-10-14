<?php
declare(strict_types=1);

namespace Ramingo\Services\PowerBi;

use Ramingo\Core\Config;
use Ramingo\Models\SettingRepository;
use Ramingo\Services\Ai\SecretBox;

/**
 * Impostazioni del collegamento a Power BI, da due fonti:
 *  - config/config.php  → se un valore è compilato lì, vince (e il pannello non lo modifica)
 *  - pannello Amministrazione → salvato nel database; il segreto è CIFRATO (SecretBox)
 */
final class PowerBiSettings
{
    public const EDITABLE = ['mode', 'tenant_id', 'client_id', 'client_secret'];
    private const GUID = '~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i';

    public function __construct(
        private readonly Config $config,
        private readonly SettingRepository $repo,
        private readonly SecretBox $box,
    ) {
    }

    /** Configurazione completa (endpoint inclusi) pronta per i client Power BI. */
    public function all(): array
    {
        $cfg = (array) $this->config->get('powerbi', []);
        foreach (self::EDITABLE as $key) {
            if ($this->source($key) === 'panel') {
                $cfg[$key] = $this->panelValue($key);
            }
        }
        $cfg['mode'] = ($cfg['mode'] ?? '') === 'live' ? 'live' : 'fixture';
        return $cfg;
    }

    public function mode(): string
    {
        return $this->all()['mode'];
    }

    /** 'config' se impostato in config.php, 'panel' se dal pannello, null se assente. */
    public function source(string $key): ?string
    {
        if (self::filled($this->config->get('powerbi.' . $key))) {
            return 'config';
        }
        return $this->repo->get('powerbi.' . $key) !== null ? 'panel' : null;
    }

    public function isComplete(): bool
    {
        $c = $this->all();
        return self::filled($c['tenant_id'] ?? '') && self::filled($c['client_id'] ?? '') && self::filled($c['client_secret'] ?? '');
    }

    /** Ultime 4 cifre del segreto, per riconoscerlo senza mostrarlo. */
    public function secretHint(): ?string
    {
        $s = (string) ($this->all()['client_secret'] ?? '');
        return $s === '' ? null : '…' . substr($s, -4);
    }

    /**
     * Salva dal pannello. Campi vuoti = invariati (il segreto non va reinserito ogni volta).
     * @return string[] errori di validazione (vuoto = salvato)
     */
    public function save(array $input): array
    {
        $errors = [];
        foreach (['tenant_id' => 'ID directory (tenant)', 'client_id' => 'ID applicazione (client)'] as $key => $label) {
            $v = trim((string) ($input[$key] ?? ''));
            if ($v === '' || $this->source($key) === 'config') {
                continue;
            }
            if (!preg_match(self::GUID, $v)) {
                $errors[] = "$label: formato non valido (atteso un codice tipo 1a2b3c4d-1111-2222-3333-444455556666).";
                continue;
            }
            $this->repo->set('powerbi.' . $key, strtolower($v));
        }
        $secret = trim((string) ($input['client_secret'] ?? ''));
        if ($secret !== '' && $this->source('client_secret') !== 'config') {
            if (!$this->box->available()) {
                $errors[] = 'Il segreto non può essere salvato cifrato: riavvia Apache dal pannello di XAMPP (serve l\'estensione sodium) e riprova.';
            } elseif (preg_match(self::GUID, $secret)) {
                $errors[] = 'Sembra che tu abbia incollato l\'«ID segreto». Serve la colonna «Valore».';
            } else {
                $this->repo->set('powerbi.client_secret', $this->box->encrypt($secret));
            }
        }
        return $errors;
    }

    public function setMode(string $mode): void
    {
        $this->repo->set('powerbi.mode', $mode === 'live' ? 'live' : 'fixture');
    }

    private function panelValue(string $key): ?string
    {
        $v = $this->repo->get('powerbi.' . $key);
        if ($v !== null && $key === 'client_secret') {
            try {
                return $this->box->decrypt($v);
            } catch (\Throwable) {
                return null; // chiave di cifratura cambiata o sodium assente: va reinserito
            }
        }
        return $v;
    }

    private static function filled(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '' && !preg_match('~^0{8}-~', $v) && !str_starts_with($v, '<');
    }
}
