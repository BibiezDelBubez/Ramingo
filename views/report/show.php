<?php
/**
 * Scheda report, su due livelli:
 *   1. per chi USA il report: cosa mostra, a chi serve, pagine, dati e indicatori in linguaggio umano
 *   2. "Dettagli tecnici" (chiusi): modello, DAX, sorgenti, permessi, storico
 *
 * @var array $r  @var string $access  @var array $notes  @var ?Ramingo\Domain\Sourced $description
 * @var array $tags  @var array $owners  @var ?string $areaPath  @var array $pages  @var array $visuals  @var array $schema
 * @var array $humanData  @var Ramingo\Models\GlossaryRepository $glossary  @var array $datasources  @var array $siblings
 * @var bool $showExpr  @var bool $canEdit  @var array $history  @var Ramingo\Services\PowerBi\PowerBiUrls $urls
 * @var array $measureNotes  @var ?array $ai  @var bool $techOpen  @var Ramingo\Services\Catalog\ReportUsage $usage
 */
use Ramingo\Domain\Source;
use Ramingo\Domain\Sourced;

$people = array_values(array_filter(array_map(static fn ($o) => $o['display_name'] ?: $o['email'], $owners)));
$ownerList = array_map(static fn ($o) => trim(($o['display_name'] ?? '') . ($o['email'] ? ' <' . $o['email'] . '>' : '')) . ' · ' . $o['access_right'], $owners);
$visibleTables = array_filter($schema, static fn ($t) => !$t['is_hidden']);
$measureCount = array_sum(array_map(static fn ($t) => count($t['measures']), $schema));
$humanTitles = static fn (array $vs) => array_values(array_unique(array_filter(array_column($vs, 'title'))));
$fieldName = static fn (array $f) => $f['entity'] !== '' ? $f['entity'] . '.' . $f['property'] : $f['property'];
?>
<nav class="small mb-2"><a href="<?= url() ?>">Catalogo</a> / <?= e($areaPath ?? 'Da classificare') ?></nav>

<div class="rg-hero d-flex flex-wrap justify-content-between gap-3">
    <div>
        <h1 class="h3 mb-1"><?= e($r['name']) ?></h1>
        <div class="text-secondary"><?= $r['report_type'] === 'PaginatedReport' ? 'Report impaginato' : 'Report' ?>
            <?php if ($r['endorsement']): ?> · <strong><?= $r['endorsement'] === 'Certified' ? 'Certificato' : 'Promosso' ?></strong><?= $r['certified_by'] ? ' da ' . e($r['certified_by']) : '' ?><?php endif ?>
        </div>
        <div class="mt-1"><?= $view->partial('partials/freshness', ['f' => $freshness, 'showUnknown' => true]) ?></div>
        <div class="mt-2"><?= $view->partial('partials/tags', ['tags' => $tags]) ?></div>
    </div>
    <div class="text-end">
        <div class="mb-2"><?= $view->partial('partials/access_badge', ['state' => $access]) ?></div>
        <?= $view->partial('partials/open_buttons', ['item' => $r]) ?>
        <?php if ($r['is_deleted']): ?><div class="alert alert-warning mt-2 py-1 small">Non più presente in Power BI all'ultima sincronizzazione.</div><?php endif ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="rg-section" id="descrizioni">
            <h2 class="h5">Cosa stai guardando</h2>
            <?= $view->partial('partials/descriptions', ['notes' => $notes, 'pbiDescription' => $r['description']]) ?>
            <?php if ($r['dataset_description'] || $r['workspace_description']): ?>
                <dl class="rg-dl mt-2">
                    <?php if ($r['dataset_description']): ?><dt>I dati</dt><dd><?= $view->partial('partials/sourced', ['s' => Sourced::pbi($r['dataset_description'])]) ?></dd><?php endif ?>
                    <?php if ($r['workspace_description']): ?><dt>Area di lavoro</dt><dd><?= $view->partial('partials/sourced', ['s' => Sourced::pbi($r['workspace_description'])]) ?></dd><?php endif ?>
                </dl>
            <?php endif ?>
            <?php if ($canEdit && !$description): ?>
                <p class="small text-secondary mt-2 mb-0">Nei «Dettagli tecnici» in fondo alla pagina trovi le proposte automatiche: puoi approvarle e pubblicarle con un clic.</p>
            <?php endif ?>
            <?php if ($canEdit): ?><?= $view->partial('partials/annotation_form', ['entityType' => 'report', 'entityId' => $r['id'], 'notes' => $notes, 'tags' => $tags, 'tagsInUse' => $tagsInUse, 'csrf' => $csrf]) ?><?php endif ?>
        </section>

        <section class="rg-section">
            <h2 class="h5">Cosa trovi nel report</h2>
            <?php if (!$pages): ?>
                <p class="text-secondary mb-0">L'elenco delle pagine non è disponibile per questo report. Aprilo in Power BI per sfogliarlo.</p>
            <?php else: ?>
                <ol class="rg-pages mb-0">
                <?php foreach ($pages as $p): $titles = $humanTitles($visuals[$p['name']] ?? []); ?>
                    <li>
                        <a href="<?= e($urls->reportPage($r['web_url'], $p['name'])) ?>" target="_blank" rel="noopener"><?= e($p['display_name']) ?></a>
                        <?php if ($titles): ?><div class="small text-secondary"><?= e(implode(' · ', array_slice($titles, 0, 8))) ?></div><?php endif ?>
                    </li>
                <?php endforeach ?>
                </ol>
            <?php endif ?>
        </section>

        <?php if ($humanData['tables'] || $humanData['measures']): ?>
        <section class="rg-section">
            <h2 class="h5">Dati e indicatori</h2>
            <?php if (!$usage->known() && $canEdit): ?>
                <p class="small text-secondary">Elenco riferito a tutto il modello semantico: Ramingo non sa ancora quali campi usa questo report
                    (vedi «Struttura del report» nei dettagli tecnici).</p>
            <?php endif ?>
            <?php if ($humanData['measures']): ?>
                <h3 class="h6">Indicatori</h3>
                <dl class="rg-dl mb-3">
                    <?php foreach ($humanData['measures'] as $m): ?>
                        <dt><?= e($m['label']) ?></dt>
                        <dd><?= $view->partial('partials/sourced', ['s' => $m['description'], 'empty' => '']) ?></dd>
                    <?php endforeach ?>
                </dl>
            <?php endif ?>
            <?php if ($humanData['tables']): ?>
                <h3 class="h6">Dati contenuti</h3>
                <dl class="rg-dl mb-0">
                    <?php foreach ($humanData['tables'] as $t): ?>
                        <dt><?= e($t['label']) ?></dt>
                        <dd><?= $view->partial('partials/sourced', ['s' => $t['description'], 'empty' => '']) ?></dd>
                    <?php endforeach ?>
                </dl>
            <?php endif ?>
        </section>
        <?php elseif ($canEdit && $schema): ?>
            <p class="small text-secondary">Per spiegare ai colleghi dati e indicatori di questo report, dai un nome comprensibile alle tabelle e alle misure nel
                <a href="<?= url('admin/glossario') ?>">Glossario</a> oppure scrivi le descrizioni nel modello in Power BI Desktop.</p>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <section class="rg-section">
            <h2 class="h5">In breve</h2>
            <dl class="rg-dl">
                <dt>Categoria</dt><dd><?= e($areaPath ?? 'Da classificare') ?></dd>
                <?php if ($people): ?><dt>Curato da</dt><dd><?= e(implode(', ', $people)) ?></dd><?php endif ?>
                <dt>Dati aggiornati</dt><dd><?= $freshness['label'] !== null && $r['data_refreshed_at']
                    ? fmt_date($r['data_refreshed_at'], true) . ($freshness['state'] === 'failed' ? ' (l\'aggiornamento successivo non è riuscito)' : '')
                    : 'non disponibile' ?></dd>
            </dl>
        </section>
        <?php if ($siblings): ?>
        <section class="rg-section">
            <h2 class="h5">Altri report sugli stessi dati</h2>
            <ul class="small mb-0">
                <?php foreach ($siblings as $s): ?><li><a href="<?= url('report/' . $s['id']) ?>"><?= e($s['name']) ?></a></li><?php endforeach ?>
            </ul>
        </section>
        <?php endif ?>
    </div>
</div>

<details class="rg-tech" id="tecnico" <?= $techOpen ? 'open' : '' ?>>
    <summary><i class="fa-solid fa-code"></i> Dettagli tecnici <span class="small text-secondary">— per chi costruisce o mantiene il report</span></summary>
    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <section class="rg-section">
                <h2 class="h6">Proposte automatiche <?= $view->partial('partials/source_badge', ['source' => Source::Auto]) ?></h2>
                <p class="small text-secondary">Testi dedotti da Ramingo o da un'AI. I colleghi non li vedono finché qualcuno non li approva.</p>
                <?= $view->partial('partials/descriptions', ['notes' => $notes, 'mode' => 'auto', 'canApprove' => $canEdit, 'entityType' => 'report', 'entityId' => $r['id']]) ?>
                <?= $view->partial('partials/ai_explain', ['ai' => $ai, 'reportId' => $r['id'], 'csrf' => $csrf]) ?>
            </section>

            <?php if ($visuals): ?>
            <section class="rg-section">
                <h2 class="h6">Visual per pagina <?= $view->partial('partials/source_badge', ['source' => Source::Definition]) ?></h2>
                <?php foreach ($pages as $p): if (empty($visuals[$p['name']])) continue; ?>
                    <div class="small fw-semibold mt-2"><?= e($p['display_name']) ?></div>
                    <ul class="rg-visuals">
                    <?php foreach ($visuals[$p['name']] as $v): ?>
                        <li><span class="rg-vtype"><?= e($v['visual_type']) ?></span> <?= e($v['title'] ?? '') ?>
                            <?php if ($v['fields']): ?><span class="text-secondary">— <?= e(implode(', ', array_map($fieldName, $v['fields']))) ?></span><?php endif ?></li>
                    <?php endforeach ?>
                    </ul>
                <?php endforeach ?>
            </section>
            <?php endif ?>

            <?php if ($schema): ?>
            <section class="rg-section">
                <h2 class="h6">Modello dati <?= $view->partial('partials/source_badge', ['source' => Source::Definition]) ?></h2>
                <p class="text-secondary small">Modello semantico «<?= e($r['dataset_name']) ?>»: <?= count($visibleTables) ?> tabelle visibili, <?= (int) $measureCount ?> misure.
                    <?php if (!$showExpr): ?>Le formule sono visibili solo a chi ha accesso al report.<?php endif ?></p>
                <?php if ($usage->known()): ?><p class="small text-secondary">In grassetto le tabelle usate da questo report; le altre fanno parte del modello ma qui non servono.</p><?php endif ?>
                <?php foreach ($schema as $t): if ($t['is_hidden'] && !$showExpr) continue; $tl = $glossary->label('table', $t['name']); $used = $usage->known() && $usage->usesTable($t['name']); ?>
                    <details class="rg-table <?= $usage->known() && !$used ? 'rg-unused' : '' ?>">
                        <summary><i class="fa-solid fa-table"></i> <code class="<?= $used ? 'fw-bold' : '' ?>"><?= e($t['name']) ?></code><?= $tl ? ' — ' . e($tl) : '' ?>
                            <span class="text-secondary small"><?= count($t['columns']) ?> colonne<?= $t['measures'] ? ', ' . count($t['measures']) . ' misure' : '' ?><?= $t['is_hidden'] ? ' · nascosta' : '' ?></span></summary>
                        <?php if ($t['description']): ?><p class="small mt-2 mb-1"><?= $view->partial('partials/sourced', ['s' => Sourced::pbi($t['description'])]) ?></p><?php endif ?>
                        <?php if ($t['measures']): ?>
                            <h3 class="h6 mt-2">Misure</h3>
                            <ul class="rg-measures">
                            <?php foreach ($t['measures'] as $m): if ($m['is_hidden'] && !$showExpr) continue; $ml = $glossary->label('measure', $m['name']); ?>
                                <li><code><?= e($m['name']) ?></code><?= $ml ? ' — ' . e($ml) : '' ?><?= $m['description'] ? ' — ' . e($m['description']) : '' ?>
                                    <?php $mx = $measureNotes[Ramingo\Services\Ai\AiAssistant::measureId($r['dataset_id'], $m['name'])]['description']['auto'] ?? null; ?>
                                    <?php if ($mx): ?><div class="rg-measure-ai"><?= $view->partial('partials/sourced', ['s' => $mx]) ?></div><?php endif ?>
                                    <?php if ($showExpr && $m['expression']): ?><pre class="rg-code"><?= e($m['expression']) ?></pre><?php endif ?></li>
                            <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                        <h3 class="h6 mt-2">Colonne</h3>
                        <p class="small"><?= e(implode(', ', array_map(
                            static fn ($c) => $c['name'] . (($cl = $glossary->label('column', $c['name'])) ? ' (' . $cl . ')' : '') . ($c['data_type'] ? ' [' . $c['data_type'] . ']' : ''),
                            array_filter($t['columns'], static fn ($c) => !$c['is_hidden'] || $showExpr)
                        ))) ?></p>
                        <?php if ($showExpr && $t['source_expression']): ?><h3 class="h6">Power Query</h3><pre class="rg-code"><?= e($t['source_expression']) ?></pre><?php endif ?>
                    </details>
                <?php endforeach ?>
            </section>
            <?php endif ?>
        </div>

        <div class="col-lg-5">
            <section class="rg-section">
                <h2 class="h6">Struttura del report <?= $view->partial('partials/source_badge', ['source' => Source::Definition]) ?></h2>
                <p class="small mb-2"><?= $usage->known()
                    ? 'Ramingo conosce i campi usati da ogni grafico: la scheda mostra solo i dati e gli indicatori di questo report.'
                    : 'Ramingo non conosce ancora i campi usati dai grafici: la scheda mostra tutto il modello semantico.' ?></p>
                <?php if ($canEdit): ?>
                <form method="post" action="<?= url('report/' . $r['id'] . '/definizione') ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center" data-busy>
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="file" name="pbix" accept=".pbix" class="form-control form-control-sm" required aria-label="File .pbix">
                    <button class="btn btn-sm btn-outline-primary"><span>Leggi dal file .pbix</span></button>
                </form>
                <p class="small text-secondary mt-1 mb-0">Utile se la sincronizzazione non riesce a leggerla da sola. Si legge solo la struttura delle pagine:
                    i dati contenuti nel file non vengono letti né conservati, e il file viene cancellato subito.</p>
                <?php endif ?>
            </section>
            <section class="rg-section">
                <h2 class="h6">Scheda tecnica</h2>
                <?= $view->partial('partials/pbi_facts', ['facts' => [
                    'Workspace'              => $r['workspace_name'],
                    'Proprietari'            => $ownerList,
                    'Creato'                 => trim(fmt_date($r['created_at']) . ' · ' . ($r['created_by'] ?? ''), ' ·'),
                    'Ultima modifica'        => trim(fmt_date($r['modified_at'], true) . ' · ' . ($r['modified_by'] ?? ''), ' ·'),
                    'Aggiornamento dati'     => $freshness['reason'] ?? trim(fmt_date($r['data_refreshed_at'] ?? null, true)
                        . (($r['data_last_status'] ?? '') !== 'Completed' && ($r['data_last_status'] ?? null) ? ' · ultimo tentativo: ' . $r['data_last_status'] . ' il ' . fmt_date($r['data_last_attempt_at'] ?? null, true) : '')),
                    'Controllato il'         => ($r['data_checked_at'] ?? null) ? fmt_date($r['data_checked_at'], true) : null,
                    'Modello semantico'      => $r['dataset_name'] ? $r['dataset_name'] . ' (' . ($r['dataset_storage_mode'] ?? '—') . ')' : null,
                    'Configurato da'         => $r['dataset_configured_by'],
                    'Etichetta riservatezza' => $r['sensitivity_label_id'] ? 'Presente' : null,
                    'ID report'              => $r['id'],
                    'Visto in Power BI il'   => fmt_date($r['last_seen_at'], true),
                ]]) ?>
            </section>
            <?php if ($datasources): ?>
            <section class="rg-section">
                <h2 class="h6">Sorgenti dati <?= $view->partial('partials/source_badge', ['source' => Source::Definition]) ?></h2>
                <ul class="small mb-0">
                    <?php foreach ($datasources as $ds): ?>
                        <li><strong><?= e($ds['datasource_type']) ?></strong> <?= e(implode(' · ', array_map('strval', $ds['details']))) ?></li>
                    <?php endforeach ?>
                </ul>
            </section>
            <?php endif ?>
            <?php if ($history): ?>
            <section class="rg-section">
                <h2 class="h6">Storico descrizioni</h2>
                <ul class="small rg-history mb-0">
                    <?php foreach ($history as $h): ?>
                        <li class="<?= $h['is_current'] ? '' : 'text-secondary' ?>"><?= fmt_date($h['created_at'], true) ?> · <?= e($h['field']) ?> · <?= e($h['author'] ?? '') ?>
                            <?= $view->partial('partials/source_badge', ['source' => Source::from($h['source'])]) ?></li>
                    <?php endforeach ?>
                </ul>
            </section>
            <?php endif ?>
        </div>
    </div>
</details>
