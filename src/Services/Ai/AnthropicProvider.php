<?php
declare(strict_types=1);

namespace Ramingo\Services\Ai;

/** API Messages di Anthropic (Claude). */
final class AnthropicProvider extends HttpAiProvider
{
    public function complete(string $system, string $user, int $maxTokens = 1500): AiReply
    {
        $json = $this->post(rtrim($this->cfg->endpoint, '/') . '/v1/messages', [
            'model'      => $this->cfg->model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $user]],
        ], [
            'x-api-key'         => $this->cfg->apiKey,
            'anthropic-version' => '2023-06-01',
        ]);
        $text = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if ($text === '') {
            throw new AiException('Risposta dell\'AI vuota o inattesa.');
        }
        return new AiReply($text, 'anthropic', $this->cfg->model);
    }
}
