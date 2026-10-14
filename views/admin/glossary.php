<?php
/**
 * @var string $kind  @var string $search  @var bool $missing  @var int $page  @var int $pages  @var int $total
 * @var array $rows  @var array $counts
 */
use Ramingo\Domain\Source;
use Ramingo\Models\GlossaryRepository;

$link = static fn (array $change) => url('admin/glossario', array_merge(['kind' => $kind, 'q' => $search, 'missing' => $missing ? '1' : null], $change));
$hint = [
    'table'   => 'Es. «VaGazTrrr» → «Vendite al dettaglio» · «Contiene una riga per ogni scontrino dei punti vendita».',
    'measure' => 'Es. «FatYoY%» → «Variazione fatturato sull\'anno precedente» · «Confronta il fatturato con lo stesso periodo dell\'anno prima».',
    'column'  => 'Es. «CdPV» → «Codice punto vendita». Le descrizioni delle colonne scritte in Power BI non arrivano a Ramingo: qui puoi scriverle.',
][$kind];
?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Glossario</nav>
<h1 class="h3 mb-1">Glossario</h1>
<p class="text-secondary small">
    Dai un nome comprensibile ai nomi tecnici del modello dati. Lo scrivi una volta e vale in tutti i modelli dove quel nome compare:
    Ramingo lo usa nelle schede dei report, nelle sintesi e nella ricerca. In alto trovi i nomi usati da più report.
    La colonna «Da Power BI» mostra la descrizione scritta nel modello, se c'è.
</p>

<ul class="nav nav-tabs mb-3">
    <?php foreach (GlossaryRepository::KINDS as $k => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $k === $kind ? 'active' : '' ?>" href="<?= e($link(['kind' => $k, 'page' => null])) ?>">
            <?= e($label) ?> <?php if ($counts[$k]): ?><span class="badge text-bg-warning"><?= (int) $counts[$k] ?> da tradurre</span><?php endif ?></a></li>
    <?php endforeach ?>
</ul>

<form method="get" action="<?= url('admin/glossario') ?>" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-sm rg-filter-input" placeholder="Cerca nome tecnico o comprensibile">
    <label class="form-check small mb-0"><input class="form-check-input" type="checkbox" name="missing" value="1" <?= $missing ? 'checked' : '' ?> data-autosubmit-check> solo da tradurre</label>
    <button class="btn btn-sm btn-outline-secondary">Filtra</button>
    <span class="small text-secondary ms-auto"><?= (int) $total ?> nomi</span>
</form>

<?php if (!$rows): ?>
    <p class="text-secondary">Nessun nome da mostrare<?= $missing ? ': sono tutti tradotti' : '' ?>.</p>
<?php else: ?>
<form method="post" action="<?= url('admin/glossario') ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="kind" value="<?= e($kind) ?>">
    <p class="small text-secondary"><?= e($hint) ?></p>
    <div class="table-responsive">
    <table class="table table-sm align-middle rg-glossary">
        <thead><tr><th>Nome tecnico</th><th>Da Power BI</th><th>Nome comprensibile</th><th>Cosa contiene / cosa calcola</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td>
                    <code><?= e($r['term']) ?></code>
                    <div class="small text-secondary">usato in <?= (int) $r['reports'] ?> report<?= $r['context'] ? ' · tabella ' . e(mb_strimwidth((string) $r['context'], 0, 60, '…')) : '' ?></div>
                    <input type="hidden" name="rows[<?= $i ?>][term]" value="<?= e($r['term']) ?>">
                    <input type="hidden" name="rows[<?= $i ?>][orig_label]" value="<?= e($r['label']) ?>">
                    <input type="hidden" name="rows[<?= $i ?>][orig_description]" value="<?= e($r['description']) ?>">
                </td>
                <td class="small"><?php if ($r['pbi_description']): ?><?= e($r['pbi_description']) ?> <?= $view->partial('partials/source_badge', ['source' => Source::PowerBi]) ?><?php else: ?><span class="text-secondary">—</span><?php endif ?></td>
                <td><input class="form-control form-control-sm" name="rows[<?= $i ?>][label]" value="<?= e($r['label']) ?>" maxlength="120" aria-label="Nome comprensibile per <?= e($r['term']) ?>"></td>
                <td><textarea class="form-control form-control-sm" name="rows[<?= $i ?>][description]" rows="1" maxlength="1000" aria-label="Descrizione per <?= e($r['term']) ?>"><?= e($r['description']) ?></textarea></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <div class="rg-sticky-save"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Salva le modifiche</button>
        <span class="small text-secondary">Vengono salvate solo le righe cambiate. Lasciare vuoti nome e descrizione toglie la voce.</span></div>
</form>
<?php if ($pages > 1): ?>
    <nav class="d-flex gap-1 mt-3">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= e($link(['page' => $p])) ?>"><?= $p ?></a>
        <?php endfor ?>
    </nav>
<?php endif ?>
<?php endif ?>
