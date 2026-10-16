<?php
declare(strict_types=1);

namespace Ramingo\Services\Catalog;

use Ramingo\Controllers\AnnotationController;
use Ramingo\Core\Database;
use Ramingo\Domain\Source;
use Ramingo\Models\AnnotationRepository;
use Ramingo\Models\AreaRepository;
use Ramingo\Models\TagRepository;

/**
 * Esporta e importa in un file per Excel (CSV con «;», UTF-8) i testi scritti a mano di tutti i report e
 * dashboard: categoria, tag, descrizione, a chi serve, referente, KPI, note.
 *
 * Regole dell'importazione (scritte anche in testa alla pagina):
 *  - cella vuota = non cambia niente;   «-» = cancella quel campo;
 *  - il report si riconosce dall'id; se l'id non c'è o non esiste, da nome + workspace;
 *  - ogni modifica è una nuova versione nello storico, come quando si salva dalla scheda.
 */
final class ManualTexts
{
    private const CLEAR = '-';

    public function __construct(
        private readonly Database $db,
        private readonly AnnotationRepository $annotations,
        private readonly TagRepository $tags,
        private readonly AreaRepository $areas,
    ) {
    }

    /** Intestazioni delle colonne, nell'ordine del file. */
    public static function headers(): array
    {
        return array_merge(['tipo', 'id', 'nome', 'workspace', 'stato', 'categoria', 'tag'], array_values(AnnotationController::editableFields()));
    }

    /** Righe del file (prima riga = intestazioni). */
    public function exportRows(): array
    {
        $fields = AnnotationController::editableFields();
        $paths  = $this->areas->paths();
        $rows   = [self::headers()];
        $items  = $this->db->all(
            "SELECT 'report' AS t, r.id, r.name, w.name AS ws, r.is_deleted, ea.area_id FROM reports r
               JOIN workspaces w ON w.id = r.workspace_id LEFT JOIN item_area ea ON ea.entity_type = 'report' AND ea.entity_id = r.id
              WHERE COALESCE(w.type, '') <> 'AdminWorkspace'   -- contenuti creati da Microsoft: fuori, come nel catalogo
                AND r.name NOT IN ('Report Usage Metrics Report', 'Dashboard Usage Metrics Report', 'Usage Metrics Report')
             UNION ALL
             SELECT 'dashboard', d.id, d.name, w.name, d.is_deleted, ea.area_id FROM dashboards d
               JOIN workspaces w ON w.id = d.workspace_id LEFT JOIN item_area ea ON ea.entity_type = 'dashboard' AND ea.entity_id = d.id
              WHERE COALESCE(w.type, '') <> 'AdminWorkspace'
             ORDER BY 5, 4, 3");
        $byType = [];
        foreach ($items as $i) {
            $byType[$i['t']][] = $i['id'];
        }
        $notes = $tags = [];
        foreach ($byType as $t => $ids) {
            $notes[$t] = $this->annotations->currentFor($t, $ids);
            $tags[$t]  = $this->tags->forEntities($t, $ids);
        }
        foreach ($items as $i) {
            $row = [$i['t'], $i['id'], $i['name'], $i['ws'], $i['is_deleted'] ? 'eliminato da Power BI' : 'attivo',
                $i['area_id'] !== null ? ($paths[(int) $i['area_id']] ?? '') : '',
                implode(', ', array_column(array_filter($tags[$i['t']][$i['id']] ?? [], static fn ($x) => $x['source'] === 'manual'), 'tag'))];
            foreach (array_keys($fields) as $f) {
                $row[] = (string) ($notes[$i['t']][$i['id']][$f][Source::Manual->value]?->value ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** CSV pronto da scaricare: «;» come separatore e BOM, così Excel in italiano lo apre correttamente. */
    public function exportCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($this->exportRows() as $row) {
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    /**
     * Applica un file. @return array{rows:int, items:int, changes:int, notFound:string[], errors:string[], touched:array}
     */
    public function import(string $content, string $by): array
    {
        $content = preg_replace('~^\xEF\xBB\xBF~', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) { // salvato da Excel come «CSV (delimitato)» = Windows-1252
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split("~\r\n|\n|\r~", (string) $content);
        $first = (string) ($lines[0] ?? '');
        $sep = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, (string) $content);
        rewind($fh);
        $head = array_map(static fn ($h) => mb_strtolower(trim((string) $h)), fgetcsv($fh, 0, $sep, '"', '') ?: []);
        $col = array_flip($head);
        $result = ['rows' => 0, 'items' => 0, 'changes' => 0, 'notFound' => [], 'errors' => [], 'touched' => []];
        if (!isset($col['id']) && !isset($col['nome'])) {
            $result['errors'][] = 'Il file non ha le colonne «id» o «nome»: usa come modello il file esportato.';
            return $result;
        }
        $fields = [];
        foreach (AnnotationController::editableFields() as $key => $label) {
            if (isset($col[mb_strtolower($label)])) {
                $fields[$key] = $col[mb_strtolower($label)];
            }
        }
        $areaByName = [];
        foreach ($this->areas->all() as $a) {
            $areaByName[mb_strtolower(trim((string) $a['name']))] = (int) $a['id'];
        }

        while (($cells = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if ($cells === [null] || !array_filter($cells, static fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            $result['rows']++;
            $get = static fn (string $name) => isset($col[$name]) ? trim((string) ($cells[$col[$name]] ?? '')) : '';
            $type = $get('tipo') === 'dashboard' ? 'dashboard' : 'report';
            $id = $this->find($type, $get('id'), $get('nome'), $get('workspace'));
            if ($id === null) {
                $result['notFound'][] = $get('nome') !== '' ? $get('nome') : $get('id');
                continue;
            }
            $changes = 0;
            foreach ($fields as $key => $i) {
                $v = trim((string) ($cells[$i] ?? ''));
                if ($v !== '') {
                    $changes += $this->annotations->save($type, $id, $key, $v === self::CLEAR ? '' : $v, Source::Manual, $by) ? 1 : 0;
                }
            }
            $tagCell = $get('tag');
            if ($tagCell !== '') {
                $before = $this->manualTags($type, $id);
                $this->tags->replace($type, $id, $tagCell === self::CLEAR ? [] : explode(',', $tagCell), Source::Manual);
                $changes += $before !== $this->manualTags($type, $id) ? 1 : 0;
            }
            $cat = $get('categoria');
            if ($cat !== '') {
                if ($cat === self::CLEAR) {
                    $this->areas->assignItem($type, $id, null, $by);
                    $changes++;
                } else {
                    $parts = preg_split('~\s*(›|>|/)\s*~u', $cat);
                    $areaId = $areaByName[mb_strtolower((string) end($parts))] ?? null;
                    $current = $this->db->value('SELECT area_id FROM item_area WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
                    if ($areaId === null) {
                        $result['errors'][] = "«{$get('nome')}»: la categoria «{$cat}» non esiste (creala prima in Categorie).";
                    } elseif ((int) $current !== $areaId) {
                        $this->areas->assignItem($type, $id, $areaId, $by);
                        $changes++;
                    }
                }
            }
            if ($changes) {
                $result['items']++;
                $result['changes'] += $changes;
                $result['touched'][] = [$type, $id];
            }
        }
        return $result;
    }

    private function find(string $type, string $id, string $name, string $ws): ?string
    {
        $table = $type === 'dashboard' ? 'dashboards' : 'reports';
        if ($id !== '' && $this->db->value("SELECT 1 FROM $table WHERE id = ?", [$id])) {
            return $id;
        }
        if ($name === '') {
            return null;
        }
        $found = $this->db->column(
            "SELECT x.id FROM $table x JOIN workspaces w ON w.id = x.workspace_id
              WHERE x.is_deleted = 0 AND lower(x.name) = lower(?) AND (? = '' OR lower(w.name) = lower(?))",
            [$name, $ws, $ws]);
        return count($found) === 1 ? (string) $found[0] : null; // più report con lo stesso nome: meglio non indovinare
    }

    private function manualTags(string $type, string $id): array
    {
        return $this->db->column("SELECT tag FROM tags WHERE entity_type = ? AND entity_id = ? AND source = 'manual' ORDER BY tag", [$type, $id]);
    }
}
