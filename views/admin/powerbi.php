<?php
/**
 * @var string $mode  @var bool $modeLocked  @var array $values  @var array $sources  @var ?string $hint
 * @var bool $complete  @var bool $canEncrypt  @var array $runs  @var bool $running  @var ?array $test
 */
$locked = static fn (string $k) => ($sources[$k] ?? null) === 'config';
?>
<nav class="small mb-2"><a href="<?= url('admin') ?>">Amministrazione</a> / Collegamento Power BI</nav>
<h1 class="h3">Collegamento Power BI</h1>

<div class="rg-hero d-flex flex-wrap align-items-center gap-3">
    <?php if ($mode === 'live'): ?>
        <span class="badge text-bg-success fs-6">Tenant reale</span>
        <span>Il catalogo mostra i report del vostro Power BI.</span>
    <?php else: ?>
        <span class="badge text-bg-warning fs-6">Dati di esempio</span>
        <span>Il catalogo mostra report <strong>inventati</strong>. Compila i tre valori qui sotto per collegarti al tuo Power BI.</span>
    <?php endif ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <section class="rg-section">
            <h2 class="h5">1. Credenziali dell'app «Ramingo-Sync»</h2>
            <p class="small text-secondary">Le trovi su portal.azure.com → Microsoft Entra ID → Registrazioni app → Ramingo-Sync.</p>
            <?php if (!$canEncrypt): ?>
                <div class="alert alert-warning small">Per salvare il segreto in modo cifrato riavvia Apache dal pannello di XAMPP (Stop, poi Start).</div>
            <?php endif ?>
            <form method="post" action="<?= url('admin/powerbi') ?>" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label" for="pbi-tenant">ID directory (tenant)</label>
                    <input class="form-control font-monospace" id="pbi-tenant" name="tenant_id" value="<?= e($values['tenant_id']) ?>"
                           placeholder="1a2b3c4d-1111-2222-3333-444455556666" <?= $locked('tenant_id') ? 'disabled' : '' ?>>
                    <div class="form-text">Pagina «Panoramica» dell'app, oppure in Power BI: ? → Informazioni su Power BI → codice dopo «ctid=».<?= $locked('tenant_id') ? ' Impostato in config.php.' : '' ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="pbi-client">ID applicazione (client)</label>
                    <input class="form-control font-monospace" id="pbi-client" name="client_id" value="<?= e($values['client_id']) ?>"
                           placeholder="1a2b3c4d-1111-2222-3333-444455556666" <?= $locked('client_id') ? 'disabled' : '' ?>>
                    <div class="form-text">Pagina «Panoramica» dell'app Ramingo-Sync.<?= $locked('client_id') ? ' Impostato in config.php.' : '' ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="pbi-secret">Segreto client (valore)</label>
                    <input class="form-control font-monospace" id="pbi-secret" name="client_secret" type="password" autocomplete="new-password"
                           placeholder="<?= $hint ? 'Salvato (' . e($hint) . ') — lascia vuoto per mantenerlo' : 'Incolla la colonna «Valore»' ?>" <?= $locked('client_secret') ? 'disabled' : '' ?>>
                    <div class="form-text">Certificati e segreti → colonna <strong>Valore</strong>, non «ID segreto». Salvato cifrato, non viene mai mostrato.</div>
                </div>
                <button class="btn btn-primary btn-sm">Salva</button>
            </form>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="rg-section">
            <h2 class="h5">2. Prova il collegamento</h2>
            <p class="small text-secondary">Controlla separatamente le credenziali e il permesso in Power BI, e dice cosa correggere.</p>
            <form method="post" action="<?= url('admin/powerbi/prova') ?>" data-busy>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="btn btn-outline-primary btn-sm" <?= $complete ? '' : 'disabled' ?>><i class="fa-solid fa-plug"></i> <span>Prova collegamento</span></button>
                <?php if (!$complete): ?><span class="small text-secondary ms-2">Compila prima i tre valori.</span><?php endif ?>
            </form>
            <?php if ($test): ?>
                <ul class="list-unstyled mt-3 mb-0">
                    <?php foreach ($test as $t): ?>
                        <li class="mb-2">
                            <i class="fa-solid <?= $t['ok'] ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                            <strong><?= e($t['step']) ?></strong><br><span class="small"><?= e($t['message']) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="rg-section">
            <h2 class="h5">3. Dati del catalogo</h2>
            <?php if ($modeLocked): ?>
                <p class="small">La modalità è fissata in <code>config/config.php</code>.</p>
            <?php else: ?>
                <form method="post" action="<?= url('admin/powerbi/modalita') ?>" class="mb-3">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="mode" value="<?= $mode === 'live' ? 'fixture' : 'live' ?>">
                    <div class="form-check small mb-2">
                        <input class="form-check-input" type="checkbox" name="confirm" value="1" id="pbi-confirm">
                        <label class="form-check-label" for="pbi-confirm">
                            Capisco che il catalogo attuale (comprese descrizioni e tag scritti a mano) verrà cancellato. Le aree aziendali restano.
                        </label>
                    </div>
                    <?php if ($mode === 'live'): ?>
                        <button class="btn btn-outline-secondary btn-sm">Torna ai dati di esempio</button>
                    <?php else: ?>
                        <button class="btn btn-success btn-sm" <?= $complete ? '' : 'disabled' ?>><i class="fa-solid fa-right-left"></i> Passa ai dati reali</button>
                    <?php endif ?>
                </form>
            <?php endif ?>

            <form method="post" action="<?= url('admin/powerbi/sync') ?>" class="d-flex gap-2" data-busy>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="btn btn-outline-primary btn-sm" name="mode" value="incremental" <?= $running ? 'disabled' : '' ?>><span>Sincronizza ora</span></button>
                <button class="btn btn-outline-secondary btn-sm" name="mode" value="full" <?= $running ? 'disabled' : '' ?>>Sincronizzazione completa</button>
            </form>

            <h3 class="h6 mt-3">Ultime sincronizzazioni</h3>
            <?php if ($running): ?><div class="alert alert-info small py-2" data-autorefresh="5">Sincronizzazione in corso… la pagina si aggiorna da sola.</div><?php endif ?>
            <?php if (!$runs): ?><p class="small text-secondary">Nessuna.</p><?php endif ?>
            <table class="table table-sm small">
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td><?= fmt_date($run['started_at'], true) ?></td>
                        <td><?= $run['mode'] === 'full' ? 'completa' : 'incrementale' ?></td>
                        <td><span class="badge text-bg-<?= $run['status'] === 'ok' ? 'success' : ($run['status'] === 'running' ? 'secondary' : 'danger') ?>"><?= e(['ok' => 'riuscita', 'running' => 'in corso', 'failed' => 'fallita'][$run['status']] ?? $run['status']) ?></span></td>
                        <td><?= $run['status'] === 'ok'
                                ? e(sprintf('%d report, %d dashboard, %d workspace', $run['stats']['reports'] ?? 0, $run['stats']['dashboards'] ?? 0, $run['stats']['workspaces'] ?? 0))
                                : e((string) $run['error']) ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </section>
    </div>
</div>
