<?php
/** Avviso nella scheda di un elemento nascosto dal catalogo. @var ?string $reason  @var bool $canEdit */
if (empty($reason)) return;
$why = ['report' => 'è stato nascosto', 'workspace' => 'il suo workspace è nascosto', 'name' => 'il nome inizia con un prefisso nascosto'][$reason] ?? '';
?>
<div class="rg-hidden-notice">
    <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
    <span>Questo elemento non compare nel catalogo né nella ricerca (<?= e($why) ?>). Chi ha il link lo apre comunque.</span>
    <?php if ($canEdit): ?><a href="<?= url('admin/nascosti') ?>">Gestisci i nascosti</a><?php endif ?>
</div>
