<?php
/** @var string $entityType  @var string $entityId  @var array $notes  @var array $tags  @var array $tagsInUse  @var string $csrf */
$tagsInUse ??= [];
use Ramingo\Domain\Source;

$manualTags = implode(', ', array_column(array_filter($tags, static fn ($t) => $t['source'] === 'manual'), 'tag'));
$hints = [
    'description'    => 'Cosa mostra il report, in parole semplici, per chi non l\'ha costruito.',
    'business_owner' => 'Chi contattare per domande sui contenuti (non sulla tecnica).',
    'audience'       => 'Es. direzione commerciale, area manager, controllo di gestione.',
    'kpi'            => 'Un KPI per riga.',
    'notes'          => 'Filtri da impostare, limiti noti, frequenza di aggiornamento.',
];
?>
<details class="rg-edit">
    <summary><i class="fa-solid fa-pen"></i> Scrivi o correggi le descrizioni <?= $view->partial('partials/source_badge', ['source' => Source::Manual]) ?></summary>
    <form method="post" action="<?= url("annotazioni/$entityType/$entityId") ?>" class="mt-3">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?php foreach (Ramingo\Controllers\AnnotationController::editableFields() as $field => $label):
            $current = $notes[$field][Source::Manual->value] ?? null; ?>
            <div class="mb-3">
                <label class="form-label" for="f-<?= e($field) ?>"><?= e($label) ?></label>
                <textarea class="form-control" id="f-<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= in_array($field, ['description', 'kpi', 'notes'], true) ? 3 : 1 ?>" maxlength="4000"><?= e($current?->value) ?></textarea>
                <div class="form-text"><?= e($hints[$field] ?? '') ?></div>
            </div>
        <?php endforeach ?>
        <div class="mb-3">
            <label class="form-label" for="f-tags">Tag</label>
            <input class="form-control" id="f-tags" name="tags" value="<?= e($manualTags) ?>" maxlength="500" data-tag-input>
            <div class="form-text">Separati da virgola. Servono per temi che attraversano più categorie (es. «lavaggi»):
                chi clicca il tag, o lo sceglie nel filtro Tag del catalogo, vede tutti i report che lo hanno.</div>
            <?php if ($tagsInUse): ?>
                <div class="rg-tags-inuse">Già usati, clicca per aggiungere:
                    <?php foreach ($tagsInUse as $t): ?>
                        <button type="button" class="rg-tag tag-manual" data-add-tag="<?= e($t['tag']) ?>">#<?= e($t['tag']) ?> <span class="rg-n"><?= (int) $t['n'] ?></span></button>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
        <button class="btn btn-primary btn-sm">Salva</button>
        <span class="small text-secondary ms-2">Ogni salvataggio è conservato nello storico. Le sintesi automatiche non vengono toccate.</span>
    </form>
</details>
