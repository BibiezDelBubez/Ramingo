<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/**
 * Vista unificata "elementi del catalogo" (report + report impaginati + dashboard)
 * con filtri. Una sola query parametrica, riusata da catalogo e ricerca.
 *
 * Restano fuori i contenuti creati da Microsoft e non dall'azienda:
 *  - il workspace «Admin monitoring» (tipo AdminWorkspace), creato da Fabric per gli amministratori;
 *  - i report «Usage Metrics», generati da Power BI quando qualcuno apre le metriche di utilizzo.
 *
 * Restano fuori anche i NASCOSTI (Amministrazione → Nascosti), salvo il filtro show_hidden:
 * report o dashboard nascosti uno per uno, interi workspace nascosti, nomi che iniziano con
 * un prefisso scelto (es. «_»). Il link diretto alla scheda funziona comunque.
 */
final class CatalogRepository extends Repository
{
    private const BASE = "
        SELECT * FROM (
            SELECT CASE WHEN r.report_type = 'PaginatedReport' THEN 'paginated' ELSE 'report' END AS kind,
                   'report' AS entity_type,
                   r.id, r.name, r.description, r.workspace_id, w.name AS workspace_name,
                   ea.area_id, a.name AS area_name, a.icon AS area_icon, a.sort_order AS area_sort,
                   r.endorsement, r.modified_at, r.modified_by, r.web_url, r.app_url, r.dataset_id,
                   dr.last_success_at AS data_refreshed_at, dr.last_attempt_status AS data_last_status,
                   dr.last_attempt_at AS data_last_attempt_at, dr.has_history AS data_has_history, dr.accessible AS data_accessible,
                   COALESCE(r.hidden, 0) AS item_hidden, COALESCE(w.hidden, 0) AS ws_hidden
              FROM reports r
              JOIN workspaces w ON w.id = r.workspace_id AND w.is_deleted = 0 AND COALESCE(w.type, '') <> 'AdminWorkspace'
         LEFT JOIN item_area ea ON ea.entity_type = 'report' AND ea.entity_id = r.id
         LEFT JOIN areas a ON a.id = ea.area_id
         LEFT JOIN dataset_refreshes dr ON dr.dataset_id = r.dataset_id
             WHERE r.is_deleted = 0
               AND r.name NOT IN ('Report Usage Metrics Report', 'Dashboard Usage Metrics Report', 'Usage Metrics Report')
            UNION ALL
            SELECT 'dashboard', 'dashboard',
                   d.id, d.name, NULL, d.workspace_id, w.name,
                   ea.area_id, a.name, a.icon, a.sort_order,
                   NULL, NULL, NULL, d.web_url, d.app_url, NULL,
                   NULL, NULL, NULL, NULL, NULL,
                   COALESCE(d.hidden, 0), COALESCE(w.hidden, 0)
              FROM dashboards d
              JOIN workspaces w ON w.id = d.workspace_id AND w.is_deleted = 0 AND COALESCE(w.type, '') <> 'AdminWorkspace'
         LEFT JOIN item_area ea ON ea.entity_type = 'dashboard' AND ea.entity_id = d.id
         LEFT JOIN areas a ON a.id = ea.area_id
             WHERE d.is_deleted = 0
        ) items";

    public const KINDS = [
        'report'    => 'Report',
        'paginated' => 'Report impaginato',
        'dashboard' => 'Dashboard',
    ];

    /**
     * Filtri accettati da all(), find() e facets() (tutti facoltativi):
     *  - area_ids int[]   categorie scelte, già espanse con le sottocategorie   } in O tra loro
     *  - area_none bool   anche «Da classificare»                               }
     *  - ws string[]      workspace (in O)
     *  - kind string[]    tipo (in O)
     *  - appr string[]    approvazione Power BI (in O)
     *  - tags string[]    tag scritti a mano: l'elemento deve averli TUTTI (in E)
     *  - fresh string     week | month | old  (data dell'ultimo aggiornamento dei dati)
     *  - keys string[]    solo questi elementi («tipo:id», es. risultati di una ricerca)
     *  - show_hidden bool anche i nascosti (vista «mostra anche i nascosti»)
     */
    public const FRESH_DAYS = ['week' => 7, 'month' => 30, 'old' => 90];

    /** Tutti gli elementi che rispettano i filtri, già ordinati (la paginazione la fa chi chiama). */
    public function all(array $f, string $sort = 'name'): array
    {
        [$where, $params] = $this->where($f);
        $rows = $this->db->all(self::BASE . $where . ' ORDER BY ' . self::order($sort), $params);
        if ($sort === 'relevance' && !empty($f['keys'])) { // stesso ordine dei risultati della ricerca
            $pos = array_flip(array_values($f['keys']));
            usort($rows, static fn ($a, $b) => ($pos[$a['entity_type'] . ':' . $a['id']] ?? PHP_INT_MAX)
                <=> ($pos[$b['entity_type'] . ':' . $b['id']] ?? PHP_INT_MAX));
        }
        return $this->withHiddenReason($rows);
    }

    /** @return array{items: array, total: int} */
    public function find(array $f, int $limit, int $offset = 0, string $sort = 'name'): array
    {
        $rows = $this->all($f, $sort);
        return ['items' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
    }

    private static function order(string $sort): string
    {
        return match ($sort) {
            'recent' => 'data_refreshed_at IS NULL, data_refreshed_at DESC, name COLLATE NOCASE',
            'area'   => 'area_id IS NULL, area_sort, area_name, name COLLATE NOCASE',
            default  => 'name COLLATE NOCASE',
        };
    }

    /** Elementi per id (mantiene l'ordine dato, es. ranking della ricerca). */
    public function byKeys(array $keys, bool $showHidden = false): array
    {
        if (!$keys) {
            return [];
        }
        $ids = array_map(static fn ($k) => explode(':', $k, 2)[1], $keys);
        [$hidden, $hp] = $this->hiddenClause();
        $rows = $this->db->all(self::BASE . ' WHERE id IN (' . Database::placeholders($ids) . ')' . ($showHidden ? '' : " AND $hidden"),
            $showHidden ? $ids : [...$ids, ...$hp]);
        $byKey = [];
        foreach ($this->withHiddenReason($rows) as $r) {
            $byKey[$r['entity_type'] . ':' . $r['id']] = $r;
        }
        return array_values(array_filter(array_map(static fn ($k) => $byKey[$k] ?? null, $keys)));
    }

    /**
     * Tutti gli elementi con la categoria assegnata AL SINGOLO elemento (own_area_id, null = segue il workspace).
     * Usato dalla gestione categorie.
     */
    public function itemsWithOwnArea(): array
    {
        return $this->db->all(
            'SELECT i.entity_type, i.kind, i.id, i.name, i.workspace_id, ia.area_id AS own_area_id
               FROM (' . self::BASE . ') i
          LEFT JOIN item_areas ia ON ia.entity_type = i.entity_type AND ia.entity_id = i.id
           ORDER BY i.name COLLATE NOCASE'
        );
    }

    /** Quanti elementi per tipo in tutto il catalogo: ['report' => 71, 'dashboard' => 4, ...]. */
    public function counts(): array
    {
        return array_map('intval', array_column(
            $this->db->all('SELECT kind, COUNT(*) AS n FROM (' . self::BASE . $this->where([])[0] . ') GROUP BY kind', $this->where([])[1]), 'n', 'kind'));
    }

    /**
     * Per la guida dell'AI: su quanti report del catalogo Ramingo conosce la struttura
     * (grafici e campi usati) e le pagine. Senza struttura l'AI ha solo nome e descrizione.
     * @return array{total:int, structure:int, pages:int}
     */
    public function aiCoverage(): array
    {
        [$where, $params] = $this->where([]);
        $row = $this->db->one(
            'SELECT COUNT(*) AS total,
                    SUM(EXISTS (SELECT 1 FROM report_visuals v WHERE v.report_id = i.id)) AS structure,
                    SUM(EXISTS (SELECT 1 FROM report_pages p WHERE p.report_id = i.id)) AS pages
               FROM (' . self::BASE . $where . ") i WHERE i.entity_type = 'report'", $params) ?? [];
        return array_map('intval', ['total' => $row['total'] ?? 0, 'structure' => $row['structure'] ?? 0, 'pages' => $row['pages'] ?? 0]);
    }

    // ---------------------------------------------------------------- nascosti

    /** Impostazione: prefissi del nome che nascondono un report (uno per riga o separati da virgola). */
    public const HIDDEN_PREFIXES = 'catalog.hidden_prefixes';

    private ?array $prefixes = null;

    /** @return string[] */
    public function hiddenPrefixes(): array
    {
        if ($this->prefixes === null) {
            $raw = (string) (new SettingRepository($this->db))->get(self::HIDDEN_PREFIXES);
            $this->prefixes = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)), 'strlen')));
        }
        return $this->prefixes;
    }

    public function setHiddenPrefixes(array $prefixes): void
    {
        $clean = array_values(array_unique(array_filter(array_map(static fn ($p) => mb_substr(trim((string) $p), 0, 40), $prefixes), 'strlen')));
        (new SettingRepository($this->db))->set(self::HIDDEN_PREFIXES, $clean ? implode("\n", $clean) : null);
        $this->prefixes = null;
    }

    /** Nasconde o rimostra un workspace, un report o una dashboard. */
    public function setHidden(string $kind, string $id, bool $hidden): void
    {
        $table = ['workspace' => 'workspaces', 'report' => 'reports', 'dashboard' => 'dashboards'][$kind]
            ?? throw new \InvalidArgumentException('Elemento non valido.');
        $this->db->run("UPDATE $table SET hidden = ? WHERE id = ?", [$hidden ? 1 : 0, $id]);
    }

    /** Quanti elementi sono nascosti in questo momento (per l'interruttore «mostra anche i nascosti»). */
    public function hiddenCount(): int
    {
        [$hidden, $p] = $this->hiddenClause();
        return (int) $this->db->value('SELECT COUNT(*) FROM (' . self::BASE . ") WHERE NOT ($hidden)", $p);
    }

    /**
     * Tutti gli elementi con lo stato di visibilità, per Amministrazione → Nascosti.
     * Ogni riga ha hidden_reason: null | 'report' | 'workspace' | 'name'.
     */
    public function visibilityList(): array
    {
        return $this->withHiddenReason($this->db->all(
            'SELECT entity_type, kind, id, name, workspace_id, workspace_name, item_hidden, ws_hidden
               FROM (' . self::BASE . ') ORDER BY workspace_name COLLATE NOCASE, name COLLATE NOCASE'));
    }

    /** Perché un singolo elemento è nascosto (null = visibile), per l'avviso nella sua scheda. */
    public function hiddenReasonFor(string $entityType, string $id): ?string
    {
        $row = $this->db->one('SELECT name, item_hidden, ws_hidden FROM (' . self::BASE . ') WHERE entity_type = ? AND id = ?', [$entityType, $id]);
        return $row ? $this->hiddenReason($row) : null;
    }

    /** 'report' (nascosto lui), 'workspace' (nascosto il suo workspace), 'name' (prefisso del nome) oppure null. */
    public function hiddenReason(array $row): ?string
    {
        if (!empty($row['item_hidden'])) {
            return 'report';
        }
        if (!empty($row['ws_hidden'])) {
            return 'workspace';
        }
        foreach ($this->hiddenPrefixes() as $p) {
            if (mb_stripos((string) $row['name'], $p) === 0) {
                return 'name';
            }
        }
        return null;
    }

    private function withHiddenReason(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['hidden_reason'] = $this->hiddenReason($r);
        }
        return $rows;
    }

    /** Condizione SQL «visibile»: né nascosto lui, né il workspace, né un prefisso del nome. */
    private function hiddenClause(): array
    {
        $sql = 'item_hidden = 0 AND ws_hidden = 0';
        $params = [];
        foreach ($this->hiddenPrefixes() as $p) {
            $sql .= " AND name NOT LIKE ? ESCAPE '\\'";
            $params[] = addcslashes($p, '\\%_') . '%';
        }
        return ['(' . $sql . ')', $params];
    }

    /**
     * Conteggi per la colonna dei filtri.
     *  - Categoria, workspace, tipo, approvazione, dati aggiornati: ognuno conta gli elementi che rispettano
     *    GLI ALTRI filtri attivi (si sceglie in O dentro lo stesso filtro).
     *  - Tag: conta gli elementi che rispettano TUTTI i filtri, tag scelti compresi (i tag si combinano in E):
     *    scelto «benelux», ogni altro tag mostra quanti report hanno anche quello.
     * @param array $f stessi filtri di all()
     */
    public function facets(array $f = []): array
    {
        $from = function (array $without) use ($f): array {
            [$where, $params] = $this->where(array_diff_key($f, array_flip($without)));
            return ['(' . self::BASE . $where . ')', $params];
        };

        [$sql, $p] = $from(['area_ids', 'area_none']);
        $areas = $this->db->all(
            "SELECT area_id AS value, COALESCE(area_name, 'Da classificare') AS label, area_icon AS icon, COUNT(*) AS n
               FROM $sql GROUP BY area_id ORDER BY area_id IS NULL, MIN(area_sort), label", $p);

        [$sql, $p] = $from(['ws']);
        $workspaces = $this->db->all(
            "SELECT workspace_id AS value, workspace_name AS label, COUNT(*) AS n FROM $sql
              GROUP BY workspace_id ORDER BY label COLLATE NOCASE", $p);

        [$sql, $p] = $from(['kind']);
        $kinds = $this->db->all("SELECT kind AS value, COUNT(*) AS n FROM $sql GROUP BY kind ORDER BY n DESC", $p);

        [$sql, $p] = $from(['appr']);
        $endorsements = $this->db->all(
            "SELECT endorsement AS value, COUNT(*) AS n FROM $sql WHERE endorsement IS NOT NULL GROUP BY endorsement", $p);

        [$sql, $p] = $from([]);
        $tags = $this->db->all(
            "SELECT t.tag AS value, t.tag AS label, COUNT(*) AS n
               FROM $sql i JOIN tags t ON t.entity_type = i.entity_type AND t.entity_id = i.id AND t.source = 'manual'
              GROUP BY t.tag ORDER BY n DESC, t.tag", $p);

        [$sql, $p] = $from(['fresh']);
        $c = self::cutoffs();
        $row = $this->db->one(
            "SELECT SUM(data_refreshed_at >= ?) AS week, SUM(data_refreshed_at >= ?) AS month, SUM(data_refreshed_at < ?) AS old FROM $sql",
            [$c['week'], $c['month'], $c['old'], ...$p]) ?? [];
        $fresh = array_map(static fn ($v) => (int) $v, array_merge(['week' => 0, 'month' => 0, 'old' => 0], array_filter($row, 'is_numeric')));

        return compact('areas', 'workspaces', 'kinds', 'endorsements', 'tags', 'fresh');
    }

    /** Soglie di data per il filtro «Dati aggiornati», in UTC come le date di Power BI. */
    private static function cutoffs(): array
    {
        return array_map(static fn (int $days) => gmdate('Y-m-d\TH:i:s\Z', time() - $days * 86400), self::FRESH_DAYS);
    }

    private function where(array $f): array
    {
        $sql = [];
        $params = [];

        $area = [];
        if (!empty($f['area_ids'])) { // categorie + sottocategorie
            // interi già validati scritti direttamente: dietro la vista item_area SQLite
            // non converte i parametri testuali, e '2' non sarebbe uguale a 2
            $area[] = 'area_id IN (' . implode(', ', array_map('intval', $f['area_ids'])) . ')';
        }
        if (!empty($f['area_none'])) {
            $area[] = 'area_id IS NULL';
        }
        if ($area) {
            $sql[] = '(' . implode(' OR ', $area) . ')';
        }

        foreach (['ws' => 'workspace_id', 'kind' => 'kind', 'appr' => 'endorsement'] as $key => $col) {
            if (!empty($f[$key])) {
                $sql[] = "$col IN (" . Database::placeholders($f[$key]) . ')';
                array_push($params, ...array_values($f[$key]));
            }
        }

        foreach ($f['tags'] ?? [] as $tag) {
            $sql[] = "EXISTS (SELECT 1 FROM tags t WHERE t.entity_type = items.entity_type AND t.entity_id = items.id
                              AND t.source = 'manual' AND t.tag = ?)";
            $params[] = mb_strtolower((string) $tag);
        }

        if (!empty($f['fresh']) && isset(self::FRESH_DAYS[$f['fresh']])) {
            $sql[] = $f['fresh'] === 'old' ? 'data_refreshed_at < ?' : 'data_refreshed_at >= ?';
            $params[] = self::cutoffs()[$f['fresh']];
        }

        if (array_key_exists('keys', $f)) {
            if (!$f['keys']) {
                $sql[] = '0';
            } else {
                $sql[] = "(entity_type || ':' || id) IN (" . Database::placeholders($f['keys']) . ')';
                array_push($params, ...array_values($f['keys']));
            }
        }
        if (empty($f['show_hidden'])) {
            [$hidden, $hp] = $this->hiddenClause();
            $sql[] = $hidden;
            array_push($params, ...$hp);
        }
        return [$sql ? ' WHERE ' . implode(' AND ', $sql) : '', $params];
    }
}
