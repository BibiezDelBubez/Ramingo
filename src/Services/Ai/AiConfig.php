<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/**
 * Configurazione di UN collegamento AI (aziendale o personale), validata.
 * Il catalogo dei fornitori supportati è qui: aggiungerne uno = una voce in PROVIDERS + un adattatore.
 */
final class AiConfig
{
    public const PROVIDERS = [
        'anthropic' => [
            'label'    => 'Anthropic Claude',
            'endpoint' => 'https://api.anthropic.com',
            'endpoint_editable' => false,
            'needs_key' => true,
            'model_label' => 'Modello (es. claude-sonnet-5-5)',
        ],
        'openai' => [
            'label'    => 'OpenAI',
            'endpoint' => 'https://api.openai.com/v1',
            'endpoint_editable' => false,
            'needs_key' => true,
            'model_label' => 'Modello (nome indicato da OpenAI)',
        ],
        'azure' => [
            'label'    => 'Azure OpenAI (tenant aziendale)',
            'endpoint' => '',
            'endpoint_editable' => true,
            'needs_key' => true,
            'model_label' => 'Nome del deployment',
        ],
        'compatible' => [
            'label'    => 'Altro compatibile OpenAI (Mistral, Gemini, Ollama, LM Studio…)',
            'endpoint' => '',
            'endpoint_editable' => true,
            'needs_key' => false,
            'model_label' => 'Modello',
        ],
    ];

    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $endpoint,
        public readonly string $apiKey,
        public readonly string $apiVersion = '2024-10-21',
    ) {
    }

    /** @param bool $strict true per le chiavi personali: solo HTTPS verso host pubblici (anti-SSRF) */
    public static function fromArray(array $a, bool $strict): self
    {
        $provider = (string) ($a['provider'] ?? '');
        $def = self::PROVIDERS[$provider] ?? throw new AiException('Fornitore AI non supportato.');
        $endpoint = $def['endpoint_editable'] ? trim((string) ($a['endpoint'] ?? '')) : $def['endpoint'];
        $cfg = new self(
            $provider,
            trim((string) ($a['model'] ?? '')),
            $endpoint,
            trim((string) ($a['api_key'] ?? '')),
            (string) ($a['api_version'] ?? '2024-10-21'),
        );
        $cfg->validate($strict);
        return $cfg;
    }

    public function label(): string
    {
        return self::PROVIDERS[$this->provider]['label'] . ' · ' . $this->model;
    }

    private function validate(bool $strict): void
    {
        if ($this->model === '' || mb_strlen($this->model) > 120) {
            throw new AiException('Indica il modello (o il deployment) da usare.');
        }
        if (self::PROVIDERS[$this->provider]['needs_key'] && $this->apiKey === '') {
            throw new AiException('Manca la chiave API.');
        }
        $parts = parse_url($this->endpoint);
        if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
            throw new AiException('Indirizzo del servizio AI non valido.');
        }
        if ($strict) {
            if ($parts['scheme'] !== 'https') {
                throw new AiException('Per le chiavi personali l\'indirizzo deve essere HTTPS.');
            }
            $host = $parts['host'];
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : null;
            if ($host === 'localhost' || ($ip && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                throw new AiException('Per le chiavi personali non sono ammessi indirizzi interni.');
            }
        }
    }
}
