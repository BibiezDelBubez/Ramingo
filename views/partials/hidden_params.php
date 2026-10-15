<?php
/** Campi nascosti che riportano i parametri della pagina in un modulo GET. @var array $params */
foreach ($params as $name => $value):
    foreach ((array) $value as $v):
        if ($v === '' || $v === null) { continue; } ?>
    <input type="hidden" name="<?= e(is_array($value) ? $name . '[]' : $name) ?>" value="<?= e((string) $v) ?>">
<?php endforeach; endforeach;
