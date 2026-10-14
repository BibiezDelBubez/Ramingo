<?php use Ramingo\Domain\Source; ?>
<h1 class="h3">Da dove arrivano le informazioni</h1>
<p class="text-secondary">Ogni informazione mostrata da Ramingo porta un'etichetta che ne indica la provenienza, perché non tutte hanno lo stesso grado di affidabilità.</p>
<div class="rg-grid">
<?php
$examples = [
    'pbi' => 'Nome del report, workspace, autore, date di creazione e modifica, certificazione, chi ha accesso, pagine del report.',
    'def' => 'Tabelle, colonne e misure del modello semantico, formule DAX, query Power Query, sorgenti dati, visual presenti nelle pagine.',
    'manual' => 'Descrizione per l\'utente di business, referente, a chi serve, KPI principali, note d\'uso, tag scelti a mano.',
    'auto' => 'Sintesi del report, KPI probabili, tag automatici, interpretazione della ricerca, stima dell\'accesso. Da verificare.',
];
foreach (Source::cases() as $s): ?>
    <article class="rg-card">
        <h2 class="h6"><?= $view->partial('partials/source_badge', ['source' => $s]) ?></h2>
        <p><?= e($s->explanation()) ?></p>
        <p class="small text-secondary mb-0"><strong>Esempi:</strong> <?= e($examples[$s->value]) ?></p>
    </article>
<?php endforeach ?>
</div>
<p class="mt-4 small text-secondary">Quando lo stesso campo ha più versioni (per esempio la descrizione), Ramingo mostra prima quella scritta a mano, poi quella di Power BI e infine quella automatica.</p>
