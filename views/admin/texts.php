<?php /** @var array $headers  @var ?array $result */ ?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Esporta e importa i testi</nav>
<h1 class="h3 mb-1">Esporta e importa i testi</h1>
<p class="text-secondary rg-measure">
    Tutti i testi scritti a mano (categoria, tag, descrizione, a chi serve, referente, KPI, note) di tutti i report
    e le dashboard, in un file da aprire con Excel. Serve per scrivere o correggere molte descrizioni insieme,
    e come copia di sicurezza.
</p>

<?php if ($result): ?>
    <section class="rg-section">
        <h2 class="h5">Risultato dell'importazione</h2>
        <p class="mb-2"><?= (int) $result['rows'] ?> righe lette · <strong><?= (int) $result['items'] ?></strong> report aggiornati · <?= (int) $result['changes'] ?> modifiche.</p>
        <?php if ($result['notFound']): ?>
            <p class="small mb-1"><strong>Non trovati</strong> (id inesistente, oppure nome ripetuto in più workspace):</p>
            <ul class="small"><?php foreach (array_slice($result['notFound'], 0, 50) as $n): ?><li><?= e($n) ?></li><?php endforeach ?></ul>
        <?php endif ?>
        <?php if ($result['errors']): ?>
            <ul class="small text-danger"><?php foreach (array_slice($result['errors'], 0, 50) as $n): ?><li><?= e($n) ?></li><?php endforeach ?></ul>
        <?php endif ?>
    </section>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-6">
        <section class="rg-section h-100">
            <h2 class="h5">1. Esporta</h2>
            <p class="small">Una riga per report o dashboard, comprese quelle eliminate da Power BI e ancora conservate.</p>
            <a class="btn btn-primary btn-sm" href="<?= url('admin/testi/esporta') ?>"><i class="fa-solid fa-download"></i> Scarica il file per Excel</a>
            <p class="small text-secondary mt-3 mb-0">Colonne: <?= e(implode(', ', $headers)) ?>.</p>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="rg-section h-100">
            <h2 class="h5">2. Modifica e reimporta</h2>
            <ul class="small">
                <li><strong>Cella vuota</strong>: quel campo non cambia.</li>
                <li><strong>«-»</strong> (un trattino): cancella quel campo.</li>
                <li><strong>Tag</strong> separati da virgola; sostituiscono i tag scritti a mano di quel report.</li>
                <li><strong>Categoria</strong>: il nome di una categoria esistente.</li>
                <li>Il report si riconosce dalla colonna <strong>id</strong>. Senza id, da <strong>nome</strong> + <strong>workspace</strong>.</li>
                <li>Ogni modifica diventa una nuova versione nello storico, come quando si salva dalla scheda.</li>
            </ul>
            <form method="post" action="<?= url('admin/testi/importa') ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center" data-busy>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="file" name="csv" accept=".csv,text/csv" class="form-control form-control-sm w-auto" required aria-label="File da importare">
                <button class="btn btn-primary btn-sm"><span>Importa</span></button>
            </form>
        </section>
    </div>
</div>
