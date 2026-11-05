<?php
/**
 * Guida dell'AI: cosa può fare, cosa no, di cosa ha bisogno. Usata nella pagina «La mia AI»
 * e nel file scaricabile (ai/guide_file), così il testo è uno solo.
 * @var array{total:int, structure:int, pages:int} $coverage  @var bool $sendDax  @var bool $allowPersonal  @var ?string $company
 */
$hl = $hl ?? 'h2';
?>
<<?= $hl ?> class="rg-guide-h">A cosa serve</<?= $hl ?>>
<p>A <strong>capire di cosa parla un report senza aprirlo</strong>, anche se nessuno gli ha scritto una descrizione,
    e a <strong>trovare il report giusto</strong> descrivendo con parole tue cosa ti serve.</p>

<<?= $hl ?> class="rg-guide-h">Cosa può fare</<?= $hl ?>>
<ul>
    <li><strong>Spiegare un report.</strong> Nella scheda del report, in fondo: «Dettagli tecnici» → «Spiega con l'AI».
        L'AI legge pagine, grafici, campi e misure del report e scrive cosa mostra, a chi serve e cosa calcolano i suoi indicatori.
        La spiegazione compare in «Proposte automatiche», con il segno «Interpretazione automatica».</li>
    <li><strong>Consigliare quale report usare.</strong> Dopo una ricerca: «Chiedi all'AI quale report usare».
        Funziona anche con domande vaghe, per esempio «chi sono i clienti che non comprano più?».
        L'AI sceglie solo tra i report che Ramingo ha trovato.</li>
    <li><strong>Spiegare come sono calcolati gli indicatori</strong>, leggendo le formule (DAX):
        <?= $sendDax ? '<strong>attivo</strong>.' : '<strong>oggi non attivo</strong>: senza le formule l\'AI deduce cosa calcola un indicatore solo dal suo nome.' ?></li>
</ul>

<<?= $hl ?> class="rg-guide-h">Cosa non può fare</<?= $hl ?>>
<ul>
    <li><strong>Dare numeri.</strong> L'AI non vede mai i dati dei report: sa cosa misura un indicatore, non quanto vale. Per i valori apri il report in Power BI.</li>
    <li><strong>Garantire di avere ragione.</strong> Interpreta, e a volte sbaglia: tutto quello che scrive è marcato «Interpretazione automatica».
        Diventa un testo ufficiale solo quando qualcuno lo controlla e lo approva.</li>
    <li><strong>Spiegare bene un report di cui Ramingo non conosce la struttura.</strong> In quel caso ha solo nome e descrizione, e la spiegazione resta vaga.</li>
    <li><strong>Modificare qualcosa in Power BI.</strong> Ramingo legge soltanto.</li>
</ul>

<<?= $hl ?> class="rg-guide-h">Di cosa ha bisogno</<?= $hl ?>>
<ul>
    <li><strong>Una chiave AI.</strong>
        <?= $company ? 'C\'è una chiave aziendale (' . e($company) . ').' : 'Non c\'è una chiave aziendale.' ?>
        <?= $allowPersonal ? 'Puoi anche usare la tua, nella pagina «La mia AI» di Ramingo: vale solo per le tue richieste.' : '' ?></li>
    <li><strong>Che Ramingo conosca la struttura del report</strong> (pagine, grafici, campi). Oggi la conosce per
        <strong><?= (int) $coverage['structure'] ?> report su <?= (int) $coverage['total'] ?></strong>.
        Per gli altri l'amministratore deve dare all'app <em>Ramingo-Sync</em> il ruolo <strong>Collaboratore</strong>
        nel workspace Power BI del report: con il ruolo Visualizzatore Power BI non gliela mostra. Poi serve una sincronizzazione.</li>
    <li><strong>Per spiegare i calcoli degli indicatori:</strong> che l'amministratore permetta di inviare le formule DAX al fornitore dell'AI
        (impostazione <code>ai.send_expressions</code> in <code>config/config.php</code>).</li>
    <li><strong>Descrizioni scritte a mano</strong>, dove ci sono, rendono le spiegazioni molto più precise.</li>
</ul>

<<?= $hl ?> class="rg-guide-h">Cosa viene inviato al fornitore dell'AI</<?= $hl ?>>
<p>Solo la descrizione dei report: nomi di report, pagine, grafici, tabelle, colonne e misure, e le descrizioni.
    <?= $sendDax ? 'Anche le formule DAX.' : 'Le formule DAX no.' ?>
    <strong>Mai i dati</strong> contenuti nei report. Ogni richiesta è registrata (chi, quando, quale fornitore).</p>
