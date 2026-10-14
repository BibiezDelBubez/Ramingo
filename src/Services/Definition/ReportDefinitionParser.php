<?php
declare(strict_types=1);

namespace Ramingo\Services\Definition;

/**
 * Estrae pagine e visual dalla DEFINIZIONE TECNICA di un report (Source::Definition),
 * con i campi usati da ogni visual: tabella, campo, tipo (misura o colonna).
 *
 * Formati supportati:
 *  - file .pbix, sia formato classico (Report/Layout) sia nuovo formato PBIR (Report/definition/...)
 *  - cartella PBIR (*.Report) salvata da Power BI Desktop come progetto
 *  - file report.json (formato classico)
 *
 * I DATI del report non vengono mai letti: solo la struttura delle pagine.
 *
 * @return array{pages: array, visuals: array}
 */
final class ReportDefinitionParser
{
    public function parsePath(string $path): array
    {
        if (is_dir($path)) {
            $files = [];
            $root = is_dir($path . '/definition') ? $path : dirname($path);
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && str_ends_with($f->getFilename(), '.json')) {
                    $files[str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = (string) file_get_contents($f->getPathname());
                }
            }
            if (isset($files['report.json']) && !$this->hasPbirPages($files)) {
                return $this->parseLegacyLayout($this->decode($files['report.json']));
            }
            return $this->parsePbir($files);
        }
        if (str_ends_with(strtolower($path), '.pbix')) {
            return $this->parsePbix($path);
        }
        return $this->parseLegacyLayout($this->decode((string) file_get_contents($path)));
    }

    /** .pbix = archivio zip: si leggono solo le parti di layout. */
    public function parsePbix(string $file): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Estensione PHP zip non attiva (abilitala in php.ini: extension=zip).');
        }
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Il file non è un .pbix valido.');
        }
        try {
            $layout = $zip->getFromName('Report/Layout');
            if ($layout !== false) {
                $json = mb_convert_encoding($layout, 'UTF-8', 'UTF-16LE');
                return $this->parseLegacyLayout($this->decode($json));
            }
            // Nuovo formato PBIR dentro il .pbix
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_starts_with($name, 'Report/definition/') && str_ends_with($name, '.json')) {
                    $files[substr($name, strlen('Report/'))] = (string) $zip->getFromIndex($i);
                }
            }
            if (!$this->hasPbirPages($files)) {
                throw new \RuntimeException('Nel .pbix non è stata trovata la struttura delle pagine.');
            }
            return $this->parsePbir($files);
        } finally {
            $zip->close();
        }
    }

    /** Formato classico: sections[] con visualContainers[] il cui config è JSON in stringa. */
    private function parseLegacyLayout(array $layout): array
    {
        $pages = $visuals = [];
        foreach ($layout['sections'] ?? [] as $i => $s) {
            $pageName = (string) ($s['name'] ?? "page$i");
            $pages[] = ['name' => $pageName, 'displayName' => (string) ($s['displayName'] ?? $pageName), 'order' => (int) ($s['ordinal'] ?? $i)];
            foreach ($s['visualContainers'] ?? [] as $vc) {
                $cfg = is_string($vc['config'] ?? null) ? json_decode($vc['config'], true) : ($vc['config'] ?? []);
                $sv  = $cfg['singleVisual'] ?? null;
                if (!$sv) {
                    continue;
                }
                $aliases = [];
                foreach ($sv['prototypeQuery']['From'] ?? [] as $from) {
                    $aliases[$from['Name'] ?? ''] = $from['Entity'] ?? '';
                }
                $fields = [];
                foreach ($sv['prototypeQuery']['Select'] ?? [] as $sel) {
                    $f = $this->field($sel, $aliases, (string) ($sel['Name'] ?? ''));
                    if ($f) {
                        $f['label'] = (string) ($sel['NativeReferenceName'] ?? $f['property']);
                        $fields[] = $f;
                    }
                }
                $visuals[] = [
                    'page'   => $pageName,
                    'type'   => (string) ($sv['visualType'] ?? 'sconosciuto'),
                    'title'  => $this->literal($sv['vcObjects']['title'][0]['properties']['text'] ?? null),
                    'fields' => $fields,
                ];
            }
        }
        return ['pages' => $pages, 'visuals' => $visuals];
    }

    /** Formato PBIR: un file per pagina e per visual. $files = [percorso relativo => contenuto]. */
    private function parsePbir(array $files): array
    {
        $order = [];
        if (isset($files['definition/pages/pages.json'])) {
            $order = array_flip($this->decode($files['definition/pages/pages.json'])['pageOrder'] ?? []);
        }
        $pages = $visuals = [];
        foreach ($files as $path => $content) {
            if (!preg_match('~^definition/pages/([^/]+)/page\.json$~', $path, $m)) {
                continue;
            }
            $p = $this->decode($content);
            $pageName = (string) ($p['name'] ?? $m[1]);
            $pages[] = ['name' => $pageName, 'displayName' => (string) ($p['displayName'] ?? $pageName), 'order' => (int) ($order[$pageName] ?? 1000 + count($pages))];

            foreach ($files as $vpath => $vcontent) {
                if (!str_starts_with($vpath, "definition/pages/{$m[1]}/visuals/") || !str_ends_with($vpath, '/visual.json')) {
                    continue;
                }
                $v = $this->decode($vcontent)['visual'] ?? [];
                $fields = [];
                foreach ($v['query']['queryState'] ?? [] as $role) {
                    foreach ($role['projections'] ?? [] as $proj) {
                        $f = $this->field($proj['field'] ?? [], [], (string) ($proj['queryRef'] ?? ''));
                        if ($f) {
                            $f['label'] = (string) ($proj['displayName'] ?? $proj['nativeQueryRef'] ?? $f['property']);
                            $fields[] = $f;
                        }
                    }
                }
                $visuals[] = [
                    'page'   => $pageName,
                    'type'   => (string) ($v['visualType'] ?? 'sconosciuto'),
                    'title'  => $this->literal($v['visualContainerObjects']['title'][0]['properties']['text'] ?? null),
                    'fields' => $fields,
                ];
            }
        }
        usort($pages, static fn ($a, $b) => $a['order'] <=> $b['order']);
        foreach ($pages as $i => &$page) {
            $page['order'] = $i;
        }
        return ['pages' => $pages, 'visuals' => $visuals];
    }

    /**
     * Da un'espressione di campo Power BI a {entity, property, kind}.
     * Gestisce Measure, Column, Aggregation, HierarchyLevel; in ultima istanza legge "Tabella.Campo".
     */
    private function field(array $node, array $aliases, string $fallbackRef): ?array
    {
        $entity = static function (array $expr) use ($aliases): string {
            $src = $expr['SourceRef'] ?? [];
            return (string) ($src['Entity'] ?? $aliases[$src['Source'] ?? ''] ?? '');
        };
        if (isset($node['Measure'])) {
            return ['entity' => $entity($node['Measure']['Expression'] ?? []), 'property' => (string) $node['Measure']['Property'], 'kind' => 'measure'];
        }
        if (isset($node['Column'])) {
            return ['entity' => $entity($node['Column']['Expression'] ?? []), 'property' => (string) $node['Column']['Property'], 'kind' => 'column'];
        }
        if (isset($node['Aggregation']['Expression'])) {
            $inner = $this->field($node['Aggregation']['Expression'], $aliases, '');
            return $inner ? ['kind' => 'column'] + $inner : null;
        }
        if (isset($node['HierarchyLevel'])) {
            $h = $node['HierarchyLevel']['Expression']['Hierarchy'] ?? [];
            return ['entity' => $entity($h['Expression'] ?? []), 'property' => (string) ($node['HierarchyLevel']['Level'] ?? ''), 'kind' => 'column'];
        }
        // "Sum(Vendite.Importo)" oppure "Vendite.Fatturato Totale"
        if (preg_match('~^(?:\w+\()?([^.()]+)\.([^()]+?)\)?$~u', $fallbackRef, $m)) {
            return ['entity' => $m[1], 'property' => $m[2], 'kind' => 'unknown'];
        }
        return null;
    }

    private function hasPbirPages(array $files): bool
    {
        foreach (array_keys($files) as $path) {
            if (preg_match('~^definition/pages/[^/]+/page\.json$~', $path)) {
                return true;
            }
        }
        return false;
    }

    /** I titoli sono espressioni: { expr: { Literal: { Value: "'Testo'" } } } */
    private function literal(?array $node): ?string
    {
        $v = $node['expr']['Literal']['Value'] ?? null;
        return is_string($v) ? trim($v, "'") : null;
    }

    private function decode(string $json): array
    {
        $data = json_decode(ltrim($json, "\xEF\xBB\xBF"), true);
        return is_array($data) ? $data : [];
    }
}
