<?php /** @var array $groups  @var string $new  @var string $test  @var array $concepts */ ?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Sinonimi di ricerca</nav>
<h1 class="h3 mb-1">Sinonimi di ricerca</h1>
<p class="text-secondary rg-measure">
    Ogni riga è un gruppo di parole che la ricerca tratta come uguali: chi cerca una di queste parole trova anche i report
    che contengono le altre. La <strong>prima parola</strong> è quella mostrata come «interpretazione».
    Nessun collega vede questa pagina: lavora dietro la ricerca. Diverso dal <a href="<?= url('admin/glossario') ?>">Glossario</a>,
    che dà un nome comprensibile ai dati.
</p>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="post" action="<?= url('admin/sinonimi') ?>" class="rg-section">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <h2 class="h5 mb-0"><?= count($groups) ?> gruppi</h2>
                <span class="small text-secondary">Parole separate da virgola. Per eliminare un gruppo, svuota la riga.</span>
            </div>
            <input type="search" class="form-control form-control-sm rg-filter-input mb-2" placeholder="Cerca una parola" aria-label="Cerca una parola" data-syn-find>
            <?php if ($new !== ''): ?>
                <label class="rg-syn-row rg-syn-new">
                    <span class="rg-syn-label">Nuovo</span>
                    <input class="form-control form-control-sm" name="groups[]" value="<?= e($new) ?>, " autofocus>
                </label>
                <p class="small text-secondary">Aggiungi dopo «<?= e($new) ?>» le parole con cui i report la chiamano, oppure copiala in un gruppo esistente.</p>
            <?php endif ?>
            <?php foreach ($groups as $i => $g): ?>
                <label class="rg-syn-row" data-syn-row>
                    <span class="rg-syn-label"><?= e($g['words'][0] ?? '') ?></span>
                    <input class="form-control form-control-sm" name="groups[]" value="<?= e(implode(', ', $g['words'])) ?>" aria-label="Gruppo <?= $i + 1 ?>">
                </label>
            <?php endforeach ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <label class="rg-syn-row">
                    <span class="rg-syn-label text-secondary">Nuovo</span>
                    <input class="form-control form-control-sm" name="groups[]" placeholder="es. lavaggio, lavaggi, pulizia cisterne" aria-label="Nuovo gruppo">
                </label>
            <?php endfor ?>
            <div class="rg-sticky-save"><button class="btn btn-primary btn-sm">Salva i sinonimi</button>
                <span class="small text-secondary">Vale subito per tutte le ricerche.</span></div>
        </form>
    </div>
    <div class="col-lg-4">
        <section class="rg-section">
            <h2 class="h5">Prova</h2>
            <form method="get" action="<?= url('admin/sinonimi') ?>" class="d-flex gap-2 mb-2">
                <input class="form-control form-control-sm" name="prova" value="<?= e($test) ?>" placeholder="es. HR costi" aria-label="Parole da provare">
                <button class="btn btn-sm btn-outline-primary">Prova</button>
            </form>
            <?php if ($test !== ''): ?>
                <?php if (!$concepts): ?>
                    <p class="small text-secondary mb-0">Nessuna parola utile: sono tutte parole ignorate (articoli, «voglio», «report»…).</p>
                <?php endif ?>
                <?php foreach ($concepts as $c): ?>
                    <p class="small mb-2"><strong><?= e($c['input']) ?></strong>
                        <?= $c['expanded'] ? '→ concetto «' . e($c['label']) . '», cerca anche: ' . e(implode(', ', $c['stems'])) : '→ nessun sinonimo, cerca solo questa parola' ?></p>
                <?php endforeach ?>
                <a class="small" href="<?= e(url('cerca', ['q' => $test])) ?>">Vedi i risultati di questa ricerca</a>
            <?php else: ?>
                <p class="small text-secondary mb-0">Scrivi una ricerca per vedere come la interpreta Ramingo.</p>
            <?php endif ?>
        </section>
    </div>
</div>
