<?php /** @var int $days  @var array $stats  @var array $empty  @var array $top */ ?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Ricerche</nav>
<h1 class="h3 mb-1">Ricerche</h1>
<p class="text-secondary rg-measure">
    Cosa cercano i colleghi nella barra di ricerca. Ramingo non registra chi ha cercato, solo il testo e quanti report ha trovato.
    Le ricerche senza risultati indicano cosa manca: un tag, una descrizione, un sinonimo nel vocabolario.
</p>

<form method="get" class="d-flex align-items-center gap-2 mb-3">
    <label for="giorni" class="small">Periodo</label>
    <select id="giorni" name="giorni" class="form-select form-select-sm w-auto" data-autosubmit>
        <?php foreach ([7 => 'Ultima settimana', 30 => 'Ultimo mese', 90 => 'Ultimi 3 mesi', 365 => 'Ultimo anno'] as $d => $l): ?>
            <option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach ?>
    </select>
    <span class="small text-secondary ms-2"><?= (int) $stats['total'] ?> ricerche, <?= (int) $stats['empty'] ?> senza risultati.</span>
</form>

<div class="row g-4">
    <div class="col-lg-7">
        <section class="rg-section">
            <h2 class="h5">Senza risultati</h2>
            <?php if (!$empty): ?>
                <p class="small text-secondary mb-0">Nessuna ricerca senza risultati in questo periodo.</p>
            <?php else: ?>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Cercato</th><th class="text-end">Volte</th><th>Ultima</th><th>Oggi trova</th></tr></thead>
                    <tbody>
                    <?php foreach ($empty as $r): ?>
                        <tr>
                            <td><a href="<?= e(url('cerca', ['q' => $r['example']])) ?>"><?= e($r['example']) ?></a></td>
                            <td class="text-end"><?= (int) $r['n'] ?></td>
                            <td class="small text-secondary"><?= fmt_date($r['last']) ?></td>
                            <td><?= $r['now'] ? '<span class="text-success"><i class="fa-solid fa-check"></i> ' . (int) $r['now'] . ' report</span>'
                                : '<span class="text-secondary">ancora niente</span> · <a href="' . e(url('admin/sinonimi', ['nuovo' => $r['example']])) . '">aggiungi come sinonimo</a>' ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <p class="small text-secondary mt-2 mb-0">«Oggi trova» rifà la ricerca adesso: dopo aver aggiunto un tag o una descrizione si vede subito se basta.</p>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="rg-section">
            <h2 class="h5">Le più cercate</h2>
            <?php if (!$top): ?>
                <p class="small text-secondary mb-0">Nessuna ricerca in questo periodo.</p>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <?php foreach ($top as $r): ?>
                        <tr><td><a href="<?= e(url('cerca', ['q' => $r['example']])) ?>"><?= e($r['example']) ?></a></td>
                            <td class="text-end"><?= (int) $r['n'] ?></td></tr>
                    <?php endforeach ?>
                </table>
            <?php endif ?>
        </section>
    </div>
</div>
