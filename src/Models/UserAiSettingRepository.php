<?php
declare(strict_types=1);

namespace Ramingo\Models;

final class UserAiSettingRepository extends Repository
{
    public function find(string $userId): ?array
    {
        return $this->db->one('SELECT * FROM user_ai_settings WHERE user_id = ?', [$userId]);
    }

    public function save(string $userId, string $provider, string $model, string $endpoint, string $keyEnc, string $hint): void
    {
        $this->db->upsert('user_ai_settings', [
            'user_id'     => $userId,
            'provider'    => $provider,
            'model'       => $model,
            'endpoint'    => $endpoint,
            'api_key_enc' => $keyEnc,
            'key_hint'    => $hint,
            'updated_at'  => now_iso(),
        ], ['user_id']);
    }

    public function delete(string $userId): void
    {
        $this->db->run('DELETE FROM user_ai_settings WHERE user_id = ?', [$userId]);
    }
}
