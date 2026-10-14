<?php /** @var array $item con web_url, app_url */ ?>
<?php if (!empty($item['app_url'])): ?>
    <a class="btn btn-primary btn-sm" href="<?= e($item['app_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri nell'app</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($item['web_url']) ?>" target="_blank" rel="noopener">Apri nel workspace</a>
<?php else: ?>
    <a class="btn btn-primary btn-sm" href="<?= e($item['web_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Apri in Power BI</a>
<?php endif;
