<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/**
 * Report e dashboard spariti da Power BI. Ramingo li conserva per un periodo (sync.purge_deleted_after_days)
 * per poter spostare i testi scritti a mano sul report che li ha sostituiti, poi li cancella del tutto.
 *
 * «Testi scritti a mano» = descrizioni e campi manuali (con il loro storico), tag manuali, categoria propria.
 */
final class DeletedItemRepository extends Repository
{
    private const TABLES = ['report' => 'reports', 'dashboard' => 'dashboards'];

    /**
     * Elementi eliminati, con quanti testi scritti a mano hanno e (se già fatto) dove sono stati spostati.
     * @return array<int, array>
     */
    public function all(): array
    {
        $rows = [];
        foreach (self::TABLES as $type => $table) {
            foreach ($this->db->all(
                "SELECT '$type' AS entity_type, x.id, x.name, x.workspace_id, w.name AS workspace_name, x.last_seen_at, x.replaced_by,
                        r2.name AS replaced_by_name
                   FROM $table x LEFT JOIN workspaces w ON w.id = x.workspace_id
              LEFT JOIN $table r2 ON r2.id = x.replaced_by
                  WHERE x.is_deleted = 1 ORDER BY x.last_seen_at DESC, x.name COLLATE NOCASE"
            ) as $r) {
                $r['manual'] = $this->manualCount($type, $r['id']);
                $rows[] = $r;
            }
        }
        usort($rows, static fn ($a, $b) => [$b['manual'] > 0, $b['last_seen_at']] <=> [$a['manual'] > 0, $a['last_seen_at']]);
        return $rows;
    }

    /** Quante cose scritte a mano ha un elemento (campi, tag, categoria propria). */
    public function manualCount(string $type, string $id): int
    {
        return (int) $this->db->value(
            "SELECT (SELECT COUNT(*) FROM annotations WHERE entity_type = ? AND entity_id = ? AND source = 'manual' AND is_current = 1)
                  + (SELECT COUNT(*) FROM tags WHERE entity_type = ? AND entity_id = ? AND source = 'manual')
                  + (SELECT COUNT(*) FROM item_areas WHERE entity_type = ? AND entity_id = ?)",
            [$type, $id, $type, $id, $type, $id]
        );
    }

    /** Elementi attivi tra cui scegliere il sostituto (stesso tipo). */
    public function activeChoices(string $type): array
    {
        $table = self::TABLES[$type];
        return $this->db->all(
            "SELECT x.id, x.name, w.name AS workspace_name FROM $table x JOIN workspaces w ON w.id = x.workspace_id
              WHERE x.is_deleted = 0 ORDER BY x.name COLLATE NOCASE");
    }

    /**
     * Sposta i testi scritti a mano da $from (eliminato) a $to (attivo). Per ogni campo, se il nuovo ne ha già
     * uno scritto a mano vince il nuovo. Lo storico delle versioni viaggia con il testo.
     * @return int cose spostate
     */
    public function moveManual(string $type, string $from, string $to): int
    {
        if (!isset(self::TABLES[$type]) || $from === $to) {
            return 0;
        }
        $table = self::TABLES[$type];
        return (int) $this->db->transaction(function (Database $db) use ($type, $table, $from, $to): int {
            $moved = 0;
            $fields = $db->column("SELECT DISTINCT field FROM annotations WHERE entity_type = ? AND entity_id = ? AND source = 'manual'", [$type, $from]);
            foreach ($fields as $field) {
                $taken = (bool) $db->value("SELECT 1 FROM annotations WHERE entity_type = ? AND entity_id = ? AND field = ? AND source = 'manual' AND is_current = 1",
                    [$type, $to, $field]);
                if (!$taken) {
                    $moved += $db->run("UPDATE annotations SET entity_id = ? WHERE entity_type = ? AND entity_id = ? AND field = ? AND source = 'manual'",
                        [$to, $type, $from, $field])->rowCount() > 0 ? 1 : 0;
                }
            }
            foreach ($db->column("SELECT tag FROM tags WHERE entity_type = ? AND entity_id = ? AND source = 'manual'", [$type, $from]) as $tag) {
                $moved += $db->run("INSERT OR IGNORE INTO tags (entity_type, entity_id, tag, source) VALUES (?, ?, ?, 'manual')", [$type, $to, $tag])->rowCount();
            }
            $db->run("DELETE FROM tags WHERE entity_type = ? AND entity_id = ? AND source = 'manual'", [$type, $from]);
            if (!$db->value('SELECT 1 FROM item_areas WHERE entity_type = ? AND entity_id = ?', [$type, $to])) {
                $moved += $db->run('UPDATE item_areas SET entity_id = ? WHERE entity_type = ? AND entity_id = ?', [$to, $type, $from])->rowCount();
            }
            $db->run("UPDATE $table SET replaced_by = ? WHERE id = ?", [$to, $from]);
            return $moved;
        });
    }

    /**
     * Ripubblicazioni evidenti: eliminato con testi a mano + attivo con LO STESSO NOME nello STESSO workspace,
     * comparso dopo, senza testi suoi. Un solo candidato, altrimenti si lascia decidere a una persona.
     * @return array<int, array{type:string, from:string, to:string, name:string}> spostamenti fatti
     */
    public function autoReplace(): array
    {
        $done = [];
        foreach (self::TABLES as $type => $table) {
            foreach ($this->db->all("SELECT id, name, workspace_id, first_seen_at FROM $table WHERE is_deleted = 1 AND replaced_by IS NULL") as $old) {
                if (!$this->manualCount($type, $old['id'])) {
                    continue;
                }
                $candidates = $this->db->all(
                    "SELECT id FROM $table WHERE is_deleted = 0 AND workspace_id = ? AND lower(name) = lower(?) AND first_seen_at >= ?",
                    [$old['workspace_id'], $old['name'], $old['first_seen_at']]);
                if (count($candidates) === 1 && !$this->manualCount($type, $candidates[0]['id'])) {
                    $this->moveManual($type, $old['id'], $candidates[0]['id']);
                    $done[] = ['type' => $type, 'from' => $old['id'], 'to' => $candidates[0]['id'], 'name' => $old['name']];
                }
            }
        }
        return $done;
    }

    /**
     * Sostituto probabile per un eliminato con testi a mano: stesso workspace o stesso modello,
     * nome uguale a meno di versioni, anni, «copia». Null se nessuno è abbastanza simile.
     */
    public function suggest(string $type, string $id): ?array
    {
        $table = self::TABLES[$type];
        $old = $this->db->one("SELECT * FROM $table WHERE id = ?", [$id]);
        if (!$old) {
            return null;
        }
        $base = self::baseName((string) $old['name']);
        $best = null;
        $bestScore = 0;
        foreach ($this->db->all("SELECT * FROM $table WHERE is_deleted = 0") as $c) {
            $score = 0;
            $score += $c['workspace_id'] === $old['workspace_id'] ? 2 : 0;
            $score += $type === 'report' && $old['dataset_id'] && $c['dataset_id'] === $old['dataset_id'] ? 2 : 0;
            similar_text($base, self::baseName((string) $c['name']), $pct);
            $score += self::baseName((string) $c['name']) === $base ? 4 : ($pct >= 75 ? 2 : 0);
            if ($score > $bestScore) {
                $best = $c;
                $bestScore = $score;
            }
        }
        return $bestScore >= 4 ? $best : null;
    }

    /** «Gemina ver 2.9 - 2025 (copia)» → «gemina». */
    private static function baseName(string $name): string
    {
        $n = mb_strtolower($name);
        $n = preg_replace(['~\b(ver(sione)?|v)\s*\.?\s*\d+([._]\d+)*\b~u', '~\b(19|20)\d{2}\b~u', '~\b(copia|copy|old|new|nuovo|test)\b~u', '~[^\p{L}\p{N}]+~u'], ' ', $n);
        return trim(preg_replace('~\s+~', ' ', (string) $n));
    }

    /**
     * Cancella del tutto gli elementi spariti da Power BI da più di $days giorni (0 = mai).
     * Con loro: testi scritti a mano, tag, categoria, permessi letti, voci di ricerca.
     * @return array{reports:int, dashboards:int, datasets:int}
     */
    public function purge(int $days): array
    {
        $out = ['reports' => 0, 'dashboards' => 0, 'datasets' => 0];
        if ($days <= 0) {
            return $out;
        }
        $limit = gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400);
        foreach (self::TABLES as $type => $table) {
            foreach ($this->db->column("SELECT id FROM $table WHERE is_deleted = 1 AND last_seen_at < ?", [$limit]) as $id) {
                $this->deleteOne($type, (string) $id);
                $out[$table]++;
            }
        }
        foreach ($this->db->column('SELECT id FROM datasets WHERE is_deleted = 1 AND last_seen_at < ?', [$limit]) as $id) {
            $this->db->transaction(function (Database $db) use ($id): void {
                $db->run("DELETE FROM annotations WHERE (entity_type = 'dataset' AND entity_id = ?) OR (entity_type = 'measure' AND entity_id LIKE ?)", [$id, $id . ':%']);
                $db->run("DELETE FROM artifact_users WHERE artifact_type = 'dataset' AND artifact_id = ?", [$id]);
                $db->run('DELETE FROM dataset_refreshes WHERE dataset_id = ?', [$id]);
                $db->run('DELETE FROM datasets WHERE id = ?', [$id]); // tabelle, colonne, misure, sorgenti a cascata
            });
            $out['datasets']++;
        }
        return $out;
    }

    /** Cancella del tutto un report o una dashboard già spariti da Power BI. */
    public function deleteOne(string $type, string $id): bool
    {
        $table = self::TABLES[$type] ?? null;
        if ($table === null || !$this->db->value("SELECT 1 FROM $table WHERE id = ? AND is_deleted = 1", [$id])) {
            return false; // mai cancellare qualcosa che in Power BI esiste ancora
        }
        $this->db->transaction(function (Database $db) use ($type, $table, $id): void {
            $db->run('DELETE FROM annotations WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
            $db->run('DELETE FROM tags WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
            $db->run('DELETE FROM item_areas WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
            $db->run('DELETE FROM artifact_users WHERE artifact_type = ? AND artifact_id = ?', [$type, $id]);
            $db->run('DELETE FROM search_docs WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
            if ($db->value("SELECT 1 FROM sqlite_master WHERE name = 'search_fts'")) {
                $db->run('DELETE FROM search_fts WHERE entity_key = ?', [$type . ':' . $id]);
            }
            $db->run("UPDATE $table SET replaced_by = NULL WHERE replaced_by = ?", [$id]);
            $db->run("DELETE FROM $table WHERE id = ?", [$id]); // pagine, grafici, riquadri a cascata
        });
        return true;
    }
}
