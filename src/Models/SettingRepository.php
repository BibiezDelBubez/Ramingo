<?php
declare(strict_types=1);

namespace Ramingo\Models;

/** Impostazioni modificabili dal pannello (tabella settings, chiave → valore). */
final class SettingRepository extends Repository
{
    public function get(string $key): ?string
    {
        $v = $this->db->value('SELECT value FROM settings WHERE key = ?', [$key]);
        return $v === null ? null : (string) $v;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->db->run('DELETE FROM settings WHERE key = ?', [$key]);
            return;
        }
        $this->db->upsert('settings', ['key' => $key, 'value' => $value], ['key']);
    }
}
