<?php
/** @var bool $entra  @var Ramingo\Services\Auth\AdminPassword $password  @var string $ip  @var string $csrf */
use Ramingo\Services\Auth\AdminPassword;
?>
<div class="rg-measure">
<h1 class="h3">Accesso amministratore</h1>

<?php if ($user->isAdmin()): ?>
    <p>
        Sei amministratore
        <?php if ($user->adminVia === 'local'): ?>perché stai usando Ramingo dalla stessa macchina su cui gira.
        <?php elseif ($user->adminVia === 'password'): ?>perché hai inserito la password. Vale finché non chiudi il browser.
        <?php else: ?>con il tuo account aziendale.<?php endif ?>
    </p>
    <p><a href="<?= url('admin') ?>">Vai ad Amministrazione</a></p>

    <?php if (!$entra): ?>
        <section class="rg-section mt-4">
            <h2 class="h5">Password di amministrazione</h2>
            <p class="small text-secondary">
                Serve per entrare in Amministrazione da un PC diverso da quello di Ramingo.
                <?php if ($password->isDefault()): ?>
                    <strong class="text-danger">Ora è ancora quella iniziale, «admin»: chiunque in rete può indovinarla. Cambiala.</strong>
                <?php elseif ($password->fromPanel()): ?>
                    È stata cambiata da qui ed è salvata cifrata (non si può rileggere).
                <?php elseif ($password->enabled()): ?>
                    Ora vale quella scritta in <code>config.php</code> (<code>auth.admin_password</code>).
                <?php else: ?>
                    Ora è spenta: da un altro PC non si entra. Impostala qui per accenderla.
                <?php endif ?>
            </p>
            <form method="post" action="<?= url('admin/password') ?>" class="d-flex flex-wrap gap-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div>
                    <label for="new" class="form-label small mb-1">Nuova password</label>
                    <input type="password" id="new" name="new" class="form-control form-control-sm" minlength="<?= AdminPassword::MIN_LENGTH ?>" required autocomplete="new-password">
                </div>
                <div>
                    <label for="confirm" class="form-label small mb-1">Ripetila</label>
                    <input type="password" id="confirm" name="confirm" class="form-control form-control-sm" minlength="<?= AdminPassword::MIN_LENGTH ?>" required autocomplete="new-password">
                </div>
                <button class="btn btn-sm btn-primary">Cambia password</button>
            </form>
            <?php if ($password->fromPanel()): ?>
                <form method="post" action="<?= url('admin/password') ?>" class="mt-2">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="reset" value="1">
                    <button class="btn btn-link btn-sm p-0">Torna alla password di config.php</button>
                </form>
            <?php endif ?>
        </section>
    <?php endif ?>

<?php elseif ($entra): ?>
    <p>Ramingo usa l'accesso con l'account aziendale: sono amministratori gli indirizzi email indicati in <code>config.php</code> (<code>auth.admin_emails</code>).</p>

<?php elseif (!$password->enabled()): ?>
    <p>Amministrazione si apre solo dal PC su cui gira Ramingo: l'accesso con password è spento.</p>

<?php else: ?>
    <p class="text-secondary">Da questo PC serve la password di amministrazione. Dal PC su cui gira Ramingo non serve.</p>
    <form method="post" action="<?= url('admin/accesso') ?>" class="d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div>
            <label for="password" class="form-label small mb-1">Password</label>
            <input type="password" id="password" name="password" class="form-control" required autofocus autocomplete="current-password">
        </div>
        <button class="btn btn-primary">Entra</button>
    </form>
<?php endif ?>
</div>
