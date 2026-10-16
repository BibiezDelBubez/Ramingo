<?php
/**
 * @var array $providers  @var ?array $personal  @var ?string $personalError  @var bool $allowPersonal
 * @var ?string $company  @var ?array $active  @var bool $sendDax
 */
$current = $personal['provider'] ?? array_key_first($providers);
?>
<h1 class="h3">La mia AI</h1>
<p class="text-secondary">Ramingo può usare un modello di intelligenza artificiale per spiegare report e misure e per suggerire quale report usare.
    Tutto ciò che produce è marcato <?= $view->partial('partials/source_badge', ['source' => Ramingo\Domain\Source::Auto]) ?> e va verificato.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="rg-section">
            <h2 class="h5">In uso adesso</h2>
            <?php if ($active): ?>
                <p class="mb-2"><strong><?= e($active['label']) ?></strong><br>
                    <span class="small text-secondary"><?= $active['owner'] === 'personal' ? 'Chiave personale' : 'Chiave aziendale' ?></span></p>
                <form method="post" action="<?= url('ai/prova') ?>" data-busy data-busy-wait="Sto provando la chiave…">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-plug"></i> <span>Prova il collegamento</span></button>
                </form>
            <?php else: ?>
                <p class="mb-0">Nessuna AI disponibile. <?= $allowPersonal ? 'Imposta qui la tua chiave' : 'Chiedi all\'amministratore' ?>.</p>
            <?php endif ?>
            <hr>
            <p class="small mb-1"><strong>Chiave aziendale:</strong> <?= $company ? e($company) : 'non configurata' ?></p>
            <p class="small mb-0"><strong>Cosa viene inviato:</strong> solo metadati (nomi di report, pagine, tabelle, colonne e misure, descrizioni).
                <?= $sendDax ? 'Anche le formule DAX.' : 'Le formule DAX no.' ?> Mai i dati contenuti nei report.
                Ogni richiesta è registrata (chi, quando, quale fornitore).</p>
        </section>

        <section class="rg-section">
            <h2 class="h5">Fornitori supportati</h2>
            <table class="table table-sm small mb-0">
                <thead><tr><th>Fornitore</th><th>Cosa serve</th></tr></thead>
                <tbody>
                    <tr><td>Anthropic Claude</td><td>chiave API (console.anthropic.com) e nome del modello</td></tr>
                    <tr><td>OpenAI</td><td>chiave API (platform.openai.com) e nome del modello</td></tr>
                    <tr><td>Azure OpenAI</td><td>endpoint della risorsa, chiave e nome del deployment (dal portale Azure aziendale)</td></tr>
                    <tr><td>Mistral, Google Gemini</td><td>«Altro compatibile OpenAI»: indirizzo del servizio, chiave e modello</td></tr>
                    <tr><td>Ollama, LM Studio (locali)</td><td>solo come chiave aziendale, configurata dall'amministratore</td></tr>
                </tbody>
            </table>
            <p class="small text-secondary mt-2 mb-0">Microsoft Copilot (M365) non fornisce una chiave API: l'equivalente aziendale è Azure OpenAI.</p>
        </section>
    </div>

    <?php if ($allowPersonal): ?>
    <div class="col-lg-7">
        <section class="rg-section">
            <h2 class="h5">La mia chiave personale</h2>
            <p class="small text-secondary">Se la imposti, Ramingo userà la tua AI al posto di quella aziendale, solo per le tue richieste.
                <?= $user->oid ? 'La chiave è salvata cifrata e non viene mai mostrata.' : 'Senza login aziendale la chiave resta solo nella sessione di questo browser e sparisce quando la chiudi.' ?></p>
            <?php if ($personalError): ?><div class="alert alert-warning small"><?= e($personalError) ?></div><?php endif ?>
            <?php if ($personal): ?>
                <p class="small">Salvata: <strong><?= e($providers[$personal['provider']]['label'] ?? $personal['provider']) ?></strong> · <?= e($personal['model']) ?> · chiave <?= e($personal['hint']) ?> · <?= e($personal['storage']) ?></p>
            <?php endif ?>

            <form method="post" action="<?= url('ai/impostazioni') ?>" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label" for="ai-provider">Fornitore</label>
                    <select class="form-select" id="ai-provider" name="provider" data-ai-provider>
                        <?php foreach ($providers as $k => $p): ?>
                            <option value="<?= e($k) ?>" <?= $k === $current ? 'selected' : '' ?>
                                data-endpoint-editable="<?= $p['endpoint_editable'] ? '1' : '0' ?>"
                                data-model-label="<?= e($p['model_label']) ?>"><?= e($p['label']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ai-model" data-ai-model-label><?= e($providers[$current]['model_label']) ?></label>
                    <input class="form-control" id="ai-model" name="model" value="<?= e($personal['model'] ?? '') ?>" required maxlength="120">
                </div>
                <div class="mb-3" data-ai-endpoint>
                    <label class="form-label" for="ai-endpoint">Indirizzo del servizio (HTTPS)</label>
                    <input class="form-control" id="ai-endpoint" name="endpoint" value="<?= e($personal['endpoint'] ?? '') ?>" placeholder="https://nome-risorsa.openai.azure.com">
                    <div class="form-text">Azure: l'endpoint della risorsa. Mistral: https://api.mistral.ai/v1 · Gemini: https://generativelanguage.googleapis.com/v1beta/openai</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ai-key">Chiave API</label>
                    <input class="form-control" id="ai-key" name="api_key" type="password" autocomplete="new-password"
                           placeholder="<?= $personal ? 'Lascia vuoto per mantenere quella salvata' : '' ?>">
                </div>
                <button class="btn btn-primary btn-sm">Salva</button>
            </form>
            <?php if ($personal): ?>
                <form method="post" action="<?= url('ai/impostazioni/elimina') ?>" class="mt-2">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="btn btn-link btn-sm text-danger p-0">Elimina la mia chiave</button>
                </form>
            <?php endif ?>
        </section>
    </div>
    <?php endif ?>
</div>
