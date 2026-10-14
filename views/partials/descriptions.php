<?php
/**
 * Campi descrittivi di un report/dashboard, in due modalità:
 *   'people' (default) → SOLO testi scritti da persone o in Power BI: è ciò che vedono i colleghi
 *   'auto'             → SOLO interpretazioni automatiche (regole o AI), con il pulsante «Approva»
 *                        che le trasforma in testo ufficiale. Si usa nei dettagli tecnici.
 *
 * @var array $notes [field][source] => Sourced  @var ?string $pbiDescription  @var string $mode
 * @var bool $canApprove  @var ?string $entityType  @var ?string $entityId
 */
use Ramingo\Controllers\AnnotationController;
use Ramingo\Domain\Source;
use Ramingo\Domain\Sourced;
use Ramingo\Models\AnnotationRepository;

$pbiDescription ??= null;
$mode ??= 'people';
$canApprove ??= false;
$any = false;
?>
<dl class="rg-dl">
<?php foreach (AnnotationRepository::FIELDS as $field => $label):
    $versions = $mode === 'auto'
        ? array_filter([$notes[$field][Source::Auto->value] ?? null])
        : array_filter([
            $notes[$field][Source::Manual->value] ?? null,
            $field === 'description' && $pbiDescription ? Sourced::pbi($pbiDescription) : null,
        ]);
    if (!$versions) continue;
    $any = true; ?>
    <dt><?= e($label) ?></dt>
    <dd><?php foreach ($versions as $s): ?>
        <div class="rg-version"><?= $view->partial('partials/sourced', ['s' => $s]) ?>
            <?php if ($mode === 'auto' && $canApprove && isset(AnnotationController::APPROVE_TO[$field])): ?>
                <form method="post" action="<?= url("annotazioni/$entityType/$entityId/approva") ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="field" value="<?= e($field) ?>">
                    <button class="btn btn-link btn-sm p-0 ms-1" title="Diventa il testo «<?= e(AnnotationRepository::FIELDS[AnnotationController::APPROVE_TO[$field]]) ?>» visibile ai colleghi">
                        <i class="fa-solid fa-check"></i> Approva e pubblica</button>
                </form>
            <?php endif ?>
        </div>
    <?php endforeach ?></dd>
<?php endforeach ?>
</dl>
<?php if (!$any): ?><p class="text-secondary mb-0"><?= $mode === 'auto' ? 'Nessuna interpretazione automatica.' : 'Nessuna descrizione scritta.' ?></p><?php endif;
