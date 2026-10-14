<?php
/** @var string $state yes|no|unknown */
[$label, $icon, $css] = Ramingo\Services\Auth\AccessService::LABELS[$state];
?><span class="access-badge <?= e($css) ?>" title="Stima di Ramingo basata sui permessi letti da Power BI. Quando apri il link, è Power BI a decidere."><i class="<?= e($icon) ?>"></i> <?= e($label) ?></span>
