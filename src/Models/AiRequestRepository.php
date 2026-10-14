<?php
declare(strict_types=1);

namespace Ramingo\Models;

/** Registro delle richieste all'AI: audit (chi ha mandato cosa a quale fornitore) e limiti d'uso. */
final class AiRequestRepository extends Repository
{
    public function log(string $userId, string $keyOwner, string $provider, string $model, string $purpose, ?string $entityId, bool $ok, ?string $error): void
    {
        $this->db->run(
            'INSERT INTO ai_requests (user_id, key_owner, provider, model, purpose, entity_id, ok, error, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $keyOwner, $provider, $model, $purpose, $entityId, (int) $ok, $error, now_iso()]
        );
    }

    public function countSince(string $userId, string $sinceIso): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM ai_requests WHERE user_id = ? AND created_at >= ?', [$userId, $sinceIso]);
    }

    public function recent(int $limit = 20): array
    {
        return $this->db->all('SELECT * FROM ai_requests ORDER BY id DESC LIMIT ' . (int) $limit);
    }
}
