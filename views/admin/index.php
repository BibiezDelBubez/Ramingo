<?php /** @var array $runs  @var bool $fts  @var string $pbiMode  @var bool $aiEnabled  @var ?string $aiCompany  @var ?string $aiCompanyError  @var array $aiRequests */ ?>
<h1 class="h3">Amministrazione</h1>

<div class="rg-grid mb-4">
    <a class="rg-card rg-admin-link" href="<?= url('admin/powerbi') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-plug"></i> Collegamento Power BI</h2>
        <span class="small text-secondary">Credenziali, prova, sincronizzazione. Ora: <strong><?= $pbiMode === 'live' ? 'tenant reale' : 'dati di esempio' ?></strong></span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/categorie') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-shapes"></i> Categorie</h2>
        <span class="small text-secondary">Albero delle categorie, assegnazione di workspace e report.</span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/glossario') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-book"></i> Glossario</h2>
        <span class="small text-secondary">Nomi comprensibili per tabelle, colonne e misure dai nomi tecnici.</span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/sinonimi') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-arrows-left-right"></i> Sinonimi di ricerca</h2>
        <span class="small text-secondary">Parole che la ricerca tratta come uguali: «HR» trova anche «personale».</span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/testi') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-file-csv"></i> Esporta e importa i testi</h2>
        <span class="small text-secondary">Descrizioni, tag e categorie di tutti i report in un file per Excel, da modificare e reimportare.</span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/eliminati') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-box-archive"></i> Eliminati da Power BI</h2>
        <span class="small text-secondary">Report spariti da Power BI: spostare i loro testi sul sostituto, eliminarli del tutto.</span>
    </a>
    <a class="rg-card rg-admin-link" href="<?= url('admin/ricerche') ?>">
        <h2 class="h6 mb-1"><i class="fa-solid fa-magnifying-glass"></i> Ricerche</h2>
        <span class="small text-secondary">Cosa cercano i colleghi e cosa non trovano: indica quali tag o descrizioni mancano.</span>
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <section class="rg-section">
            <h2 class="h5">Sincronizzazioni</h2>
            <p class="small text-secondary">Ricerca full-text: <?= $fts ? 'FTS5 attivo' : 'LIKE (FTS5 non disponibile)' ?>.</p>
            <?php if (!$runs): ?><p class="small text-secondary">Nessuna sincronizzazione.</p><?php endif ?>
            <table class="table table-sm small">
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><?= fmt_date($run['started_at'], true) ?></td>
                        <td><?= e($run['mode']) ?></td>
                        <td><span class="badge text-bg-<?= $run['status'] === 'ok' ? 'success' : ($run['status'] === 'running' ? 'secondary' : 'danger') ?>"><?= e($run['status']) ?></span></td>
                        <td><?= e($run['error'] ?? implode(', ', array_map(static fn ($k, $v) => "$k: $v", array_keys($run['stats']), $run['stats']))) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="rg-section">
            <h2 class="h5">Intelligenza artificiale</h2>
            <?php if (!$aiEnabled): ?>
                <p class="small">Disattivata (<code>ai.enabled</code> in config.php).</p>
            <?php else: ?>
                <p class="small mb-1">Chiave aziendale: <strong><?= $aiCompany ? e($aiCompany) : 'non configurata' ?></strong></p>
                <?php if (!$aiCompany && $aiCompanyError): ?><p class="small text-secondary"><?= e($aiCompanyError) ?></p><?php endif ?>
                <h3 class="h6 mt-3">Ultime richieste</h3>
                <?php if (!$aiRequests): ?><p class="small text-secondary">Nessuna.</p><?php endif ?>
                <table class="table table-sm small">
                    <?php foreach ($aiRequests as $q): ?>
                        <tr class="<?= $q['ok'] ? '' : 'table-danger' ?>">
                            <td><?= fmt_date($q['created_at'], true) ?></td>
                            <td><?= e($q['user_id']) ?></td>
                            <td><?= e($q['purpose']) ?></td>
                            <td><?= e($q['provider'] . ' · ' . $q['model']) ?></td>
                            <td><?= e($q['error'] ?? 'ok') ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            <?php endif ?>
        </section>
    </div>
</div>
