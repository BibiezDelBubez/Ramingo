<?php
/** @var array $rows  @var array $choices  @var int $days */
$withTexts = array_filter($rows, static fn ($r) => $r['manual'] > 0 && !$r['replaced_by']);
?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Eliminati da Power BI</nav>
<h1 class="h3 mb-1">Eliminati da Power BI</h1>
<p class="text-secondary rg-measure">
    Report e dashboard che non esistono più in Power BI. Non compaiono nel catalogo, ma Ramingo li conserva
    <?= $days > 0 ? "per $days giorni" : 'senza scadenza (cancellazione automatica disattivata)' ?> per poter recuperare
    i testi scritti a mano. Se un report è stato ripubblicato con <strong>lo stesso nome nello stesso workspace</strong>,
    i testi passano al nuovo da soli alla sincronizzazione. Negli altri casi scegli qui sotto il sostituto.
</p>
<p class="small text-secondary rg-measure">
    <?php if ($days > 0): ?>
        Dopo <?= $days ?> giorni dalla scomparsa vengono cancellati del tutto durante la sincronizzazione
        (impostazione <code>sync.purge_deleted_after_days</code> in config.php; 0 = mai).
    <?php else: ?>
        Per attivare la cancellazione automatica imposta <code>sync.purge_deleted_after_days</code> in config.php (es. 90).
    <?php endif ?>
</p>

<?php if (!$rows): ?>
    <div class="rg-empty"><p class="mb-0">Nessun elemento eliminato da Power BI.</p></div>
<?php else: ?>
<form method="post" action="<?= url('admin/eliminati/sposta') ?>" class="rg-section">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th class="rg-col-check"></th><th>Nome</th><th>Sparito dopo il</th><th>Testi scritti a mano</th><th>Sostituto</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $key = $r['entity_type'] . '|' . $r['id']; ?>
            <tr>
                <td>
                    <?php if ($r['manual'] > 0 && !$r['replaced_by']): ?>
                        <input type="checkbox" class="form-check-input" name="pick[]" value="<?= e($key) ?>" <?= $r['suggest'] ? 'checked' : '' ?> aria-label="Sposta i testi di <?= e($r['name']) ?>">
                    <?php endif ?>
                </td>
                <td><strong><?= e($r['name']) ?></strong>
                    <div class="small text-secondary"><i class="fa-solid fa-cloud rg-ico-ws"></i> <?= e($r['workspace_name'] ?? '—') ?><?= $r['entity_type'] === 'dashboard' ? ' · dashboard' : '' ?></div></td>
                <td class="small"><?= fmt_date($r['last_seen_at']) ?></td>
                <td class="small"><?= $r['manual'] ? (int) $r['manual'] : '<span class="text-secondary">nessuno</span>' ?></td>
                <td>
                    <?php if ($r['replaced_by']): ?>
                        <span class="small text-success"><i class="fa-solid fa-check"></i> spostati su
                            <a href="<?= e(url($r['entity_type'] . '/' . $r['replaced_by'])) ?>"><?= e($r['replaced_by_name'] ?? 'report') ?></a></span>
                    <?php elseif ($r['manual'] > 0): ?>
                        <select class="form-select form-select-sm" name="target[<?= e($key) ?>]" aria-label="Sostituto di <?= e($r['name']) ?>">
                            <option value="">— scegli il sostituto —</option>
                            <?php foreach ($choices[$r['entity_type']] as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= $r['suggest'] && $r['suggest']['id'] === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['workspace_name']) ?>)</option>
                            <?php endforeach ?>
                        </select>
                        <?php if ($r['suggest']): ?><div class="small text-secondary mt-1">Proposto da Ramingo: nome e workspace simili.</div><?php endif ?>
                    <?php else: ?>
                        <span class="small text-secondary">niente da spostare</span>
                    <?php endif ?>
                </td>
                <td class="text-end"><button class="btn btn-sm btn-outline-danger text-nowrap" form="del-<?= e(md5($key)) ?>">Elimina subito</button></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php if ($withTexts): ?>
        <div class="rg-sticky-save"><button class="btn btn-primary btn-sm">Sposta i testi delle righe spuntate</button>
            <span class="small text-secondary">Per ogni campo, se il sostituto ne ha già uno scritto a mano resta il suo.</span></div>
    <?php endif ?>
</form>
<?php foreach ($rows as $r): $key = $r['entity_type'] . '|' . $r['id']; ?>
    <form id="del-<?= e(md5($key)) ?>" method="post" action="<?= url('admin/eliminati/elimina') ?>" data-confirm="Eliminare del tutto «<?= e($r['name']) ?>» da Ramingo? Testi, tag e storico andranno persi.">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="type" value="<?= e($r['entity_type']) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
    </form>
<?php endforeach ?>
<?php endif ?>
