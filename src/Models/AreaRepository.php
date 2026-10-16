<?php
declare(strict_types=1);

namespace Ramingo\Models;

use Ramingo\Core\Database;

/**
 * Categorie aziendali (dati manuali): albero con sottocategorie, assegnabili
 * a un intero workspace o al singolo report/dashboard (che vince sul workspace).
 */
final class AreaRepository extends Repository
{
    public const ICONS = [
        'fa-solid fa-shapes', 'fa-solid fa-handshake', 'fa-solid fa-coins', 'fa-solid fa-truck', 'fa-solid fa-industry',
        'fa-solid fa-people-group', 'fa-solid fa-compass', 'fa-solid fa-server', 'fa-solid fa-chart-line', 'fa-solid fa-warehouse',
        'fa-solid fa-gas-pump', 'fa-solid fa-flask', 'fa-solid fa-shield-halved', 'fa-solid fa-leaf', 'fa-solid fa-cart-shopping',
        'fa-solid fa-calendar-days', 'fa-solid fa-ship', 'fa-solid fa-screwdriver-wrench', 'fa-solid fa-building', 'fa-solid fa-star',
    ];

    /** Impostazione: come ordinare le categorie nel catalogo ('alpha' = alfabetico, assente = come nell'elenco). */
    public const ORDER_SETTING = 'catalog.category_order';

    /** Vero se nel catalogo le categorie vanno in ordine alfabetico invece che nell'ordine scelto a mano. */
    public function alphabetical(): bool
    {
        return (new SettingRepository($this->db))->get(self::ORDER_SETTING) === 'alpha';
    }

    public function setAlphabetical(bool $on): void
    {
        (new SettingRepository($this->db))->set(self::ORDER_SETTING, $on ? 'alpha' : null);
    }

    public function all(): array
    {
        return $this->db->all('SELECT * FROM areas ORDER BY sort_order, name COLLATE NOCASE');
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM areas WHERE id = ?', [$id]);
    }

    /**
     * Albero: ogni nodo ha 'children' e 'depth'. Ordinato per sort_order, oppure per nome
     * (a ogni livello) se $alphabetical.
     * @return array<int, array>
     */
    public function tree(bool $alphabetical = false): array
    {
        $byParent = [];
        foreach ($this->all() as $a) {
            $byParent[(int) ($a['parent_id'] ?? 0)][] = $a;
        }
        if ($alphabetical) {
            foreach ($byParent as &$siblings) {
                usort($siblings, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
            }
            unset($siblings);
        }
        $build = function (int $parent, int $depth) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $a) {
                $a['depth'] = $depth;
                $a['children'] = $build((int) $a['id'], $depth + 1);
                $out[] = $a;
            }
            return $out;
        };
        return $build(0, 0);
    }

    /** Albero "appiattito" in ordine di visualizzazione (per select e filtri). */
    public function flat(bool $alphabetical = false): array
    {
        $out = [];
        $walk = function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $n) {
                $children = $n['children'];
                unset($n['children']);
                $out[] = $n;
                $walk($children);
            }
        };
        $walk($this->tree($alphabetical));
        return $out;
    }

    /** Ordine delle categorie nel catalogo, secondo l'impostazione scelta in amministrazione. */
    public function catalogTree(): array
    {
        return $this->tree($this->alphabetical());
    }

    public function catalogFlat(): array
    {
        return $this->flat($this->alphabetical());
    }

    /** Percorso leggibile di ogni categoria: [id => "Commerciale › Agenti"]. */
    public function paths(): array
    {
        $all = [];
        foreach ($this->all() as $a) {
            $all[(int) $a['id']] = $a;
        }
        $paths = [];
        foreach ($all as $id => $a) {
            $names = [];
            for ($cur = $a, $guard = 0; $cur && $guard < 20; $cur = $all[(int) ($cur['parent_id'] ?? 0)] ?? null, $guard++) {
                array_unshift($names, $cur['name']);
            }
            $paths[$id] = implode(' › ', $names);
        }
        return $paths;
    }

    /** La categoria e tutte le sue discendenti (per filtrare il catalogo). */
    public function subtreeIds(int $id): array
    {
        $ids = [$id];
        for ($i = 0; $i < count($ids); $i++) {
            foreach ($this->db->column('SELECT id FROM areas WHERE parent_id = ?', [$ids[$i]]) as $child) {
                $ids[] = (int) $child;
            }
        }
        return $ids;
    }

    public function create(string $name, ?int $parentId, ?string $icon): int
    {
        $this->db->run(
            'INSERT INTO areas (name, slug, icon, parent_id, sort_order) VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 10 FROM areas))',
            [$name, self::slug($name), $this->validIcon($icon), $parentId]
        );
        return (int) $this->db->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $this->db->run('UPDATE areas SET name = ?, slug = ? WHERE id = ?', [$name, self::slug($name), $id]);
    }

    public function setIcon(int $id, string $icon): void
    {
        $this->db->run('UPDATE areas SET icon = ? WHERE id = ?', [$this->validIcon($icon), $id]);
    }

    /**
     * Sposta una categoria sotto un'altra (o al primo livello con $parentId = null),
     * opzionalmente prima di una categoria sorella. Impedisce i cicli.
     */
    public function move(int $id, ?int $parentId, ?int $beforeId = null): void
    {
        if ($parentId !== null && in_array($parentId, $this->subtreeIds($id), true)) {
            throw new \InvalidArgumentException('Una categoria non può stare dentro sé stessa o dentro una sua sottocategoria.');
        }
        $this->db->transaction(function (Database $db) use ($id, $parentId, $beforeId): void {
            $siblings = $db->column(
                'SELECT id FROM areas WHERE ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = ?') . ' AND id <> ? ORDER BY sort_order, name COLLATE NOCASE',
                $parentId === null ? [$id] : [$parentId, $id]
            );
            $pos = $beforeId !== null ? array_search($beforeId, array_map('intval', $siblings), true) : false;
            array_splice($siblings, $pos === false ? count($siblings) : $pos, 0, [$id]);
            $db->run('UPDATE areas SET parent_id = ? WHERE id = ?', [$parentId, $id]);
            foreach ($siblings as $i => $sid) {
                $db->run('UPDATE areas SET sort_order = ? WHERE id = ?', [($i + 1) * 10, (int) $sid]);
            }
        });
    }

    /**
     * Elimina una categoria. Sottocategorie e contenuti passano a $moveTo
     * (oppure al genitore della categoria eliminata, oppure restano da classificare).
     */
    public function delete(int $id, ?int $moveTo): void
    {
        $area = $this->find($id) ?? throw new \InvalidArgumentException('Categoria inesistente.');
        if ($moveTo !== null && in_array($moveTo, $this->subtreeIds($id), true)) {
            throw new \InvalidArgumentException('Non puoi spostare i contenuti dentro la categoria che stai eliminando.');
        }
        $target = $moveTo ?? ($area['parent_id'] !== null ? (int) $area['parent_id'] : null);
        $this->db->transaction(function (Database $db) use ($id, $target): void {
            $db->run('UPDATE areas SET parent_id = ? WHERE parent_id = ?', [$target, $id]);
            if ($target === null) {
                $db->run('DELETE FROM workspace_areas WHERE area_id = ?', [$id]);
                $db->run('DELETE FROM item_areas WHERE area_id = ?', [$id]);
            } else {
                $db->run('UPDATE workspace_areas SET area_id = ? WHERE area_id = ?', [$target, $id]);
                $db->run('UPDATE item_areas SET area_id = ? WHERE area_id = ?', [$target, $id]);
            }
            $db->run('DELETE FROM areas WHERE id = ?', [$id]);
        });
    }

    public function assignWorkspace(string $workspaceId, ?int $areaId, string $by): void
    {
        if ($areaId === null) {
            $this->db->run('DELETE FROM workspace_areas WHERE workspace_id = ?', [$workspaceId]);
            return;
        }
        $this->db->upsert('workspace_areas', [
            'workspace_id' => $workspaceId,
            'area_id'      => $areaId,
            'assigned_by'  => $by,
            'assigned_at'  => now_iso(),
        ], ['workspace_id']);
    }

    /** Categoria del singolo report/dashboard. null = torna a seguire il workspace. */
    public function assignItem(string $entityType, string $entityId, ?int $areaId, string $by): void
    {
        if ($areaId === null) {
            $this->db->run('DELETE FROM item_areas WHERE entity_type = ? AND entity_id = ?', [$entityType, $entityId]);
            return;
        }
        $this->db->upsert('item_areas', [
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'area_id'     => $areaId,
            'assigned_by' => $by,
            'assigned_at' => now_iso(),
        ], ['entity_type', 'entity_id']);
    }

    /** Numero di elementi del catalogo per categoria (diretti). */
    public function itemCounts(): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT ea.area_id, COUNT(*) AS n FROM item_area ea
               LEFT JOIN reports r ON ea.entity_type = 'report' AND r.id = ea.entity_id
               LEFT JOIN dashboards d ON ea.entity_type = 'dashboard' AND d.id = ea.entity_id
              WHERE ea.area_id IS NOT NULL AND COALESCE(r.is_deleted, d.is_deleted) = 0
           GROUP BY ea.area_id"
        ) as $r) {
            $out[(int) $r['area_id']] = (int) $r['n'];
        }
        return $out;
    }

    private function validIcon(?string $icon): string
    {
        return in_array($icon, self::ICONS, true) ? $icon : 'fa-solid fa-shapes';
    }

    private static function slug(string $name): string
    {
        return trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($name)), '-') . '-' . substr(md5($name . microtime()), 0, 4);
    }
}
