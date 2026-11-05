<?php
/** @var array $workspaces  @var string[] $prefixes  @var int $total  @var string $csrf */
$why = ['report' => 'nascosto', 'workspace' => 'per il workspace', 'name' => 'per il nome'];
$button = static function (string $kind, string $id, string $wsId, bool $hidden, string $label) use ($csrf): string {
    return '<form method="post" action="' . e(url('admin/nascosti/imposta')) . '" class="d-inline">'
        . '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
        . '<input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="id" value="' . e($id) . '">'
        . '<input type="hidden" name="ws" value="' . e($wsId) . '"><input type="hidden" name="hidden" value="' . ($hidden ? '0' : '1') . '">'
        . '<button class="rg-vis-btn" aria-label="' . e(($hidden ? 'Mostra ' : 'Nascondi ') . $label) . '">'
        . ($hidden ? '<i class="fa-solid fa-eye"></i> Mostra' : '<i class="fa-solid fa-eye-slash"></i> Nascondi') . '</button></form>';
};
?>
<input type="hidden" id="rg-csrf" value="<?= e($csrf) ?>">
<div class="rg-toast" id="rg-toast" hidden></div>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Nascosti</nav>
<h1 class="h3">Nascosti dal catalogo</h1>
<p class="text-secondary">
    Report e dashboard utili a chi lavora sui dati ma non ai colleghi: qui li togli dal catalogo, dalla ricerca e dai suggerimenti.
    Chi ha il link diretto li apre comunque, e in fondo ai filtri c'è «Mostra anche i nascosti» per chi deve ritrovarli.
    Non è una protezione: i dati restano protetti dai permessi di Power BI.
</p>
<p><strong><?= (int) $total ?></strong> elementi nascosti in questo momento.</p>

<section class="rg-section">
    <h2 class="h5 mb-1"><i class="fa-solid fa-wand-magic"></i> Regola automatica: nascondi per nome</h2>
    <p class="small text-secondary">
        Nascondi i report il cui nome <strong>inizia</strong> con uno di questi prefissi, anche quelli pubblicati in futuro.
        Uno per riga, per esempio <code>_</code> oppure <code>[TEC]</code>. Lascia vuoto per non usare la regola.
    </p>
    <form method="post" action="<?= url('admin/nascosti/regole') ?>" class="d-flex flex-wrap gap-2 align-items-start">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <textarea name="prefixes" rows="3" class="form-control form-control-sm rg-prefixes" aria-label="Prefissi"><?= e(implode("\n", $prefixes)) ?></textarea>
        <button class="btn btn-sm btn-primary">Salva la regola</button>
    </form>
</section>

<section class="rg-section">
    <h2 class="h5 mb-1"><i class="fa-solid fa-eye-slash"></i> Nascondi workspace o singoli report</h2>
    <p class="small text-secondary">
        Nascondere un workspace nasconde tutto quello che contiene, anche i report pubblicati in futuro.
        I workspace con elementi nascosti sono aperti.
    </p>
    <input type="search" class="form-control form-control-sm mb-3" placeholder="Filtra per workspace o report" data-filter-list="#rg-vis-list" aria-label="Filtra">
    <div id="rg-vis-list">
        <?php foreach ($workspaces as $ws): ?>
            <details class="rg-vis-ws<?= $ws['hidden'] ? ' is-hidden' : '' ?>" id="ws-<?= e($ws['id']) ?>" <?= $ws['hiddenItems'] ? 'open' : '' ?>
                     data-filter-text="<?= e(mb_strtolower($ws['name'] . ' ' . implode(' ', array_column($ws['items'], 'name')))) ?>"
                     data-filter-ws="<?= e(mb_strtolower($ws['name'])) ?>">
                <summary>
                    <i class="fa-solid fa-cloud text-secondary" aria-hidden="true"></i>
                    <span class="rg-vis-name"><?= e($ws['name']) ?></span>
                    <span class="rg-vis-why"><?= $ws['hiddenItems'] ?> di <?= count($ws['items']) ?> nascosti</span>
                    <?= $button('workspace', $ws['id'], $ws['id'], $ws['hidden'], 'il workspace ' . $ws['name']) ?>
                </summary>
                <ul class="rg-vis-items rg-group-items">
                    <?php foreach ($ws['items'] as $it): $r = $it['hidden_reason']; ?>
                        <li class="<?= $r ? 'is-hidden' : '' ?>" data-filter-name="<?= e(mb_strtolower($it['name'])) ?>">
                            <a class="rg-vis-item" href="<?= url($it['entity_type'] . '/' . $it['id']) ?>"><?= e($it['name']) ?></a>
                            <?php if ($r): ?><span class="rg-vis-why"><i class="fa-solid fa-eye-slash"></i> <?= e($why[$r]) ?></span><?php endif ?>
                            <?php if ($r === null || $r === 'report'): ?>
                                <?= $button($it['entity_type'], $it['id'], $ws['id'], $r === 'report', $it['name']) ?>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </details>
        <?php endforeach ?>
    </div>
</section>
