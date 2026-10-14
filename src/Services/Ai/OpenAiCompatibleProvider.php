<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/**
 * Protocollo "chat completions" di OpenAI, adottato anche da Azure OpenAI, Mistral,
 * Google Gemini (endpoint compatibile), Ollama e LM Studio.
 */
final class OpenAiCompatibleProvider extends HttpAiProvider
{
    public function complete(string $system, string $user, int $maxTokens = 1500): AiReply
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.2,
        ];
        if ($this->cfg->provider === 'azure') {
            // Azure: il "modello" è il nome del deployment e la chiave va nell'header api-key
            $url = rtrim($this->cfg->endpoint, '/') . '/openai/deployments/' . rawurlencode($this->cfg->model)
                 . '/chat/completions?api-version=' . rawurlencode($this->cfg->apiVersion);
            $headers = ['api-key' => $this->cfg->apiKey];
        } else {
            $payload['model'] = $this->cfg->model;
            $url = rtrim($this->cfg->endpoint, '/') . '/chat/completions';
            $headers = $this->cfg->apiKey !== '' ? ['Authorization' => 'Bearer ' . $this->cfg->apiKey] : [];
        }

        $json = $this->post($url, $payload, $headers);
        $text = $json['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new AiException('Risposta dell\'AI vuota o inattesa.');
        }
        return new AiReply($text, $this->cfg->provider, $this->cfg->model);
    }
}
