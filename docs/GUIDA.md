# Ramingo — guida per chi lo gestisce

Questa guida spiega, senza gergo tecnico, come funziona Ramingo e cosa devi sapere per gestirlo.
Per i dettagli di programmazione c'è [ARCHITETTURA.md](ARCHITETTURA.md).

---

## 1. In una frase

Ramingo legge da Power BI l'elenco di report e dashboard dell'azienda, ci aggiunge le spiegazioni
scritte da voi e lo presenta ai colleghi come un catalogo con ricerca. **Non mostra mai i dati dei report**:
per vederli si clicca «Apri in Power BI», e lì valgono i permessi di ciascuno.

---

## 2. Da dove arriva ogni informazione

Ogni informazione in Ramingo ha un'etichetta colorata che dice da dove viene:

| Etichetta | Significa | Esempi | Chi la aggiorna |
|---|---|---|---|
| **Power BI** | letta da Power BI | nome, autore, date, certificazione, descrizioni scritte in Power BI | la sincronizzazione |
| **Definizione tecnica** | letta dalla struttura del modello | tabelle, colonne, misure, formule DAX | la sincronizzazione |
| **Scritto a mano** | scritto da qualcuno in Ramingo | descrizioni, referente, glossario, categorie, tag | voi |
| **Interpretazione automatica** | dedotta da Ramingo o da un'AI | sintesi, KPI probabili, spiegazioni AI | Ramingo; **i colleghi non la vedono** finché non la approvi |

Quando per la stessa cosa ci sono più versioni, Ramingo mostra prima quella **scritta a mano**, poi quella
**di Power BI**, poi quella **automatica**.

La sincronizzazione **non tocca mai** quello che avete scritto voi.

---

## 3. Come Ramingo riconosce un report o una dashboard

Ogni report e dashboard ha in Power BI un **codice univoco** (es. `1a2b3c4d-…`), quello che vedi nell'indirizzo
della pagina. Ramingo usa quel codice, **non il nome**. Quindi:

| Cosa fai in Power BI | Cosa succede in Ramingo |
|---|---|
| **Rinomini** il report | Stesso report: si aggiorna il nome, le descrizioni restano. |
| **Lo scarichi, lo modifichi in Desktop e lo ripubblichi** nello stesso workspace scegliendo **«Sostituisci»** | Stesso report: Power BI mantiene il codice, quindi Ramingo lo vede come lo stesso. Si aggiornano data di modifica, pagine e modello; le vostre descrizioni restano. |
| Lo ripubblichi con **un altro nome** o in **un altro workspace** | Per Power BI è un report **nuovo** (codice nuovo). In Ramingo compare un nuovo report senza descrizioni; il vecchio, se lo elimini, sparisce. |
| **Salvi una copia** | Report nuovo, codice nuovo. |
| **Elimini** il report | Alla sincronizzazione successiva sparisce dal catalogo. Le descrizioni scritte in Ramingo **restano salvate** nel database (non si perdono), ma non sono più visibili. |
| Lo pubblichi anche **in un'app** | È lo stesso report: Ramingo mostra due pulsanti, «Apri nell'app» e «Apri nel workspace». |

> **Consiglio:** quando aggiorni un report, ripubblica sempre **con lo stesso nome, nello stesso workspace,
> scegliendo «Sostituisci»**. Così indirizzi, preferiti dei colleghi e descrizioni in Ramingo restano validi.

Il comportamento di «Sostituisci» è quello documentato dalla comunità Power BI; se un giorno notassi un report
«duplicato» dopo una ripubblicazione, è segno che è stato creato un oggetto nuovo.

---

## 4. Dove sono salvati i dati di Ramingo

Tutto è in **un solo file**: `Ramingo\storage\ramingo.sqlite` (più due file di appoggio `-wal` e `-shm`).

- **Backup:** copia i tre file `storage\ramingo.sqlite*` mentre non è in corso una sincronizzazione.
  È l'unica cosa da salvare: contiene descrizioni, glossario, categorie.
- Le **impostazioni** stanno in `config\config.php` (con la chiave che cifra i segreti): salvalo a parte,
  in un posto sicuro. Senza quella chiave i segreti salvati nel pannello vanno reinseriti.
- Né il database né `config.php` vanno su GitHub: il file `.gitignore` li esclude già.

---

## 5. La sincronizzazione

È il momento in cui Ramingo chiede a Power BI cosa c'è. Due tipi:

| Tipo | Cosa fa | Quando |
|---|---|---|
| **Completa** | rilegge tutti i workspace, si accorge anche di quelli eliminati | ogni notte |
| **Incrementale** | rilegge solo i workspace modificati dall'ultima volta | ogni ora |

Si avvia da **Amministrazione → Collegamento Power BI**, oppure si pianifica con l'Utilità di pianificazione
di Windows (comandi nel [README](../README.md)).

### 5.1 «Dati aggiornati» e «modificato»: due date diverse

| Data | Cosa significa | Dove si vede |
|---|---|---|
| **Dati aggiornati al…** | ultimo aggiornamento riuscito dei **dati** (refresh del modello semantico). In Power BI è la colonna «Aggiornato» dell'elenco del workspace | schede del catalogo, pagina del report, ordinamento «Dati più recenti» |
| **Ultima modifica** | ultima volta che qualcuno ha ripubblicato o modificato il **report** (pagine, grafici) | solo nei Dettagli tecnici |

Ai colleghi serve la prima: dice se i numeri sono di stamattina o di un anno fa.

- La data è grigia se recente, **ambra** se più vecchia di 7 giorni (`catalog.stale_days`),
  **rossa** se l'ultimo aggiornamento non è riuscito: in quel caso la data è quella dell'ultimo riuscito.
- Ramingo la legge durante la sincronizzazione, al massimo una volta l'ora per modello (`sync.refresh_check_minutes`).
- **Requisito:** Ramingo-Sync deve essere membro (Visualizzatore) del workspace **del modello semantico**,
  che può essere diverso da quello del report: per i report collegati al Modello Semantico Master è il workspace Utility.
  Se non lo è, la data non compare e nei Dettagli tecnici è spiegato perché.
- Nessuna data anche per i modelli che Power BI non aggiorna mai (dati letti in tempo reale, DirectQuery).

### 5.2 Report eliminati da Power BI

Quando un report sparisce da Power BI, Ramingo lo toglie dal catalogo ma lo **conserva** per
`sync.purge_deleted_after_days` giorni (90; **0 = mai cancellare**), poi lo cancella del tutto durante una
sincronizzazione. Nessuna attività pianificata: la pulizia avviene quando parte una sincronizzazione.

- **Ripubblicato con lo stesso nome nello stesso workspace** (Power BI gli ha dato un nuovo identificativo):
  alla sincronizzazione i testi scritti a mano (categoria, descrizioni con il loro storico, tag) passano
  da soli al nuovo report, se il nuovo non ne ha di suoi.
- **Negli altri casi**: Amministrazione → **Eliminati da Power BI**. Ramingo propone il sostituto probabile
  (nome simile a meno di versione e anno, stesso workspace o modello); spunti le righe giuste e premi
  «Sposta i testi delle righe spuntate». Per ogni campo, se il sostituto ne ha già uno scritto a mano resta il suo.
- «Elimina subito» cancella del tutto un singolo elemento (chiede conferma).

### 5.3 Esporta e importa i testi (Excel)

Amministrazione → **Esporta e importa i testi**: un file CSV per Excel con una riga per report o dashboard
(categoria, tag, descrizione, a chi serve, referente, KPI, note). Lo modifichi e lo reimporti.
Cella vuota = nessun cambiamento; «-» = cancella il campo; il report si riconosce dall'id o, senza id,
da nome + workspace. Ogni modifica entra nello storico. Utile anche come copia di sicurezza.

---

## 6. Scrivere le descrizioni direttamente in Power BI (consigliato)

Il modo migliore è scrivere le spiegazioni **una volta sola, in Power BI**: Ramingo le legge da solo.

| Cosa | Dove la scrivi | Arriva in Ramingo? |
|---|---|---|
| **Report** | app.powerbi.com → report → ⋯ → *Impostazioni* → **Descrizione** | Sì, come «Cosa stai guardando» |
| **Modello semantico** | app.powerbi.com → modello → *Impostazioni* → **Descrizione** | Sì, come «I dati» |
| **Workspace** | app.powerbi.com → workspace → *Impostazioni area di lavoro* → **Descrizione** | Sì, come «Area di lavoro» |
| **Tabelle** | Power BI Desktop → vista **Modello** → clic sulla tabella → pannello **Proprietà** → **Descrizione**, poi pubblica | Sì, in «Dati contenuti» |
| **Misure** | come le tabelle, cliccando la misura | Sì, in «Indicatori» |
| **Colonne** | come le tabelle | **No**: Power BI non le espone. Usa il Glossario di Ramingo. |
| **Dashboard** | non esiste un campo descrizione | **No**: scrivila in Ramingo. |
| **Pagine dei report** | non esiste un campo descrizione | **No**: dai alle pagine nomi chiari (es. «Vendite per regione», non «Pagina 3»). |

Perché funzioni nel modello servono le impostazioni del tenant «metadati dettagliati» (tabelle, misure) e,
per le formule, «espressioni DAX e mashup».

### 6.1 Le descrizioni sopravvivono quando ripubblico il report?

Dipende da **dove** è scritta la descrizione. La regola da ricordare: *ripubblicando da Desktop, tutto ciò che sta
dentro il file .pbix viene sostituito con il contenuto del file che pubblichi*.

| Descrizione | Dove vive | Se ripubblichi con «Sostituisci» |
|---|---|---|
| **Tabelle e misure** (scritte in Desktop, vista Modello) | **dentro il .pbix** | Vince ciò che c'è nel file che pubblichi. Se le hai scritte nel file, restano. Se qualcuno le ha scritte **online** (modifica del modello nel servizio) e tu ripubblichi un file vecchio che non le contiene, **si perdono**. |
| **Report** (Impostazioni → Descrizione, online) | nel servizio Power BI, **non** nel .pbix | Il report mantiene lo stesso codice; ci si aspetta che le impostazioni restino, ma Microsoft non lo documenta esplicitamente: **verificalo una volta** (vedi sotto). |
| **Modello semantico** (Impostazioni → Descrizione, online) | nel servizio Power BI | Come sopra: da verificare una volta. |
| **Workspace** | nel servizio Power BI | Non toccata: la ripubblicazione non riguarda il workspace. |
| **Tutto ciò che scrivi in Ramingo** (descrizioni, glossario, categorie, tag) | nel database di Ramingo | **Mai sovrascritto** da Power BI, purché il report mantenga lo stesso codice (ripubblicazione con «Sostituisci», vedi §3). |

**Le altre informazioni che compaiono sia nel catalogo OneLake di Power BI sia in Ramingo**

| Informazione | Dove si imposta | Se ripubblichi con «Sostituisci» |
|---|---|---|
| **Workspace** in cui sta il report | — | Invariato (è lo stesso report). |
| **Dominio** (Fabric) | si assegna al **workspace**, non al report | Invariato: la ripubblicazione non tocca il workspace. |
| **Certificazione / Promozione** | impostazioni del report o del modello, online | È un'impostazione dell'oggetto, che resta lo stesso: dovrebbe restare. Da verificare una volta insieme alla descrizione. |
| **Proprietario / ultima modifica** | automatico | Cambia: diventa chi ha ripubblicato, con la data della ripubblicazione. Per il modello semantico, chi ripubblica ne diventa di solito anche il «configurato da». |
| **App Power BI** | app del workspace | **Attenzione:** l'app mostra ancora la versione precedente finché qualcuno non preme **«Aggiorna app»** nel workspace. Chi apre il report dall'app vede la versione vecchia. |

**Regole pratiche per non perdere nulla**

1. Prima di modificare un report, **scarica sempre l'ultima versione** dal servizio (non usare una copia vecchia sul PC):
   così il file contiene anche le modifiche fatte da altri.
2. Scrivi le descrizioni di **tabelle e misure in Desktop**, nel file, e salvalo: viaggiano con il report.
3. Ripubblica sempre **con lo stesso nome, nello stesso workspace, scegliendo «Sostituisci»**.
4. Per i testi importanti destinati ai colleghi, Ramingo è il posto più sicuro: lì nessuna ripubblicazione li tocca.

**Verifica una tantum (5 minuti)** — per sapere con certezza come si comporta il vostro tenant:
scrivi «PROVA» nella descrizione online di un report di prova (e nel suo modello semantico), segnalo come «Promosso»,
scaricalo, ripubblicalo con «Sostituisci», poi riapri le impostazioni: se «PROVA» e la promozione ci sono ancora,
descrizioni e certificazioni online sopravvivono alla ripubblicazione.

Nella comunità Power BI sono segnalati anche casi di descrizioni dei campi sparite dopo un aggiornamento dei dati,
su alcuni modelli: è un difetto noto di Power BI, non di Ramingo. Se succede, alla sincronizzazione successiva
la descrizione sparisce anche da Ramingo, perché Ramingo mostra ciò che Power BI gli restituisce; i testi scritti in Ramingo invece restano.

---

### 6.2 Quali dati e indicatori usa ciascun report

Se molti report si appoggiano allo **stesso modello semantico** (per esempio un «Modello Semantico Master»),
il modello da solo non dice quale report usa cosa: «Turnover dipendenti» e «On Time Delivery» avrebbero
lo stesso elenco di tabelle e misure. Per questo Ramingo legge anche la **struttura di ogni report**:
pagine, grafici e i campi usati da ciascun grafico. Così la scheda mostra **solo** i dati e gli indicatori
di quel report, e la ricerca non confonde report diversi costruiti sullo stesso modello.

Ramingo la ottiene in due modi:

1. **Automatico, durante la sincronizzazione.** Ramingo scarica il report *senza dati* (modalità
   «connessione dinamica») e ne legge la struttura; il file viene cancellato subito. Funziona solo se
   l'app **Ramingo-Sync è aggiunta al workspace** del report (in genere basta *Visualizzatore*; se il
   riepilogo della sincronizzazione segnala report «non accessibili», prova *Collaboratore*) e se il
   download dei report non è bloccato dalle impostazioni del tenant. Si rilegge solo quando il report cambia.
2. **A mano.** Nella scheda del report → *Dettagli tecnici* → **Struttura del report** → «Leggi dal file .pbix».
   Si legge solo la struttura delle pagine: i dati nel file non vengono letti né conservati.

Finché la struttura non è nota, la scheda lo dice e mostra l'elenco completo del modello.

## 7. Il glossario

Serve quando i nomi tecnici sono incomprensibili (`VaGazTrrr`, `FatYoY%`, `CdPV`).
**Amministrazione → Glossario**: per ogni nome tecnico scrivi un **nome comprensibile** e, se serve,
una **descrizione** («Contiene una riga per ogni scontrino dei punti vendita»).

- Vale **per nome**, in tutti i modelli: se `VaGazTrrr` compare in tre modelli, lo traduci una volta.
- In alto ci sono i nomi usati da più report: parti da lì.
- Dove esiste anche una descrizione scritta in Power BI, il glossario ha la precedenza (è più recente e scritto per i colleghi).
- Il glossario rende comprensibili: la scheda del report («Dati e indicatori»), la sintesi automatica,
  la ricerca (si trova «vendite al dettaglio» anche se la tabella si chiama `VaGazTrrr`) e i testi dell'AI.

---

## 8. Le categorie

**Amministrazione → Categorie**.

**Workspace e categorie non sono la stessa cosa.**

| | Workspace | Categoria |
|---|---|---|
| Cos'è | una cartella di Power BI (icona nuvola) | un argomento, per i colleghi (icona forme) |
| Dice | *dove* è salvato il report | *di cosa parla* il report |
| Chi lo decide | chi pubblica in Power BI | tu, in Ramingo |
| Cambiarlo in Ramingo | non si può: Ramingo lo legge e basta | sì, e in Power BI non cambia niente |

Il legame è una scorciatoia: assegnando una categoria a un workspace, tutti i suoi report la prendono;
un singolo report può poi averne una diversa. Le categorie conviene farle per argomento
(«Operations e logistica», «Amministrazione e finanza», «Commerciale»), non copiare i nomi dei workspace:
altrimenti si ottiene un doppione del filtro Workspace.

- Le categorie formano un **albero** (es. *Commerciale › Agenti*). Per **cambiare l'ordine** usa le frecce accanto a
  ogni categoria, oppure trascinala sul bordo alto o basso di un'altra (compare una linea blu). Lasciata **al centro**
  di un'altra ci finisce dentro; trascinala sulla fascia «Primo livello» per riportarla su. Doppio clic sul nome per rinominarla.
- **Ordine nel catalogo**: in cima al riquadro scegli se il catalogo (gruppi «Per categoria» e colonna dei filtri)
  segue l'ordine dell'elenco o l'ordine alfabetico. Con poche categorie conviene l'elenco, con le più usate in cima;
  con molte, l'alfabetico. «Da classificare» resta sempre in fondo.
- A destra, «Assegna i Report alle categorie», i report sono raggruppati per workspace **solo per ritrovarli**:
  i workspace non si possono modificare da Ramingo, che di Power BI ha solo permessi di lettura.
  Per ogni workspace c'è una scorciatoia, «Categoria per tutto il workspace» (vale anche per i report pubblicati in futuro);
  sotto, ogni report può avere una categoria sua. I workspace con report ancora da classificare sono aperti e segnalati in giallo.
- Eliminando una categoria scegli dove spostare il contenuto e le sottocategorie.
- Due categorie non possono avere lo stesso nome, anche se stanno in rami diversi.
- Nel catalogo, filtrando per una categoria si vedono anche i report delle sue sottocategorie.

---

## 9. Cosa vedono i colleghi

La scheda di ogni report ha due livelli:

1. **In alto, per chi usa il report:** solo testi scritti da una persona (in Ramingo o nel glossario) o in Power BI:
   cosa mostra, a chi serve, referente, le pagine, dati e indicatori spiegati.
2. **In fondo, chiusi, i «Dettagli tecnici»:** modello, formule, sorgenti, permessi, storico e le **proposte automatiche**
   (sintesi di Ramingo, testi dell'AI). Una proposta diventa visibile ai colleghi solo quando premi **«Approva e pubblica»**:
   da quel momento è un testo ufficiale «Scritto a mano», che puoi sempre correggere.

Anche nelle schede del catalogo compaiono solo descrizioni scritte da persone o in Power BI, e solo i tag scritti a mano.

L'indicazione «Hai accesso / Probabilmente senza accesso» è una **stima**: la decisione vera la prende Power BI
quando si apre il report.

### 9.1 La pagina iniziale, la ricerca e i filtri

Catalogo e risultati di ricerca sono **la stessa pagina**: in alto la ricerca («Quale report ti serve?»),
a sinistra la colonna dei filtri, al centro i report. Gli esempi sotto la ricerca si cambiano in
`config/config.php` → `catalog.examples`.

| Filtro | Come si combina | Note |
|---|---|---|
| **Categoria** | più categorie = report dell'una **o** dell'altra | scegliere una categoria include le sue sottocategorie |
| **Tag** | più tag = report che li hanno **tutti** | scelto «benelux», accanto agli altri tag compare quanti report hanno anche quello |
| **Dati aggiornati** | una scelta | ultima settimana, ultimo mese, più vecchi di 3 mesi |
| **Workspace** | più workspace = **o** | chiuso di default: è dove sta il report in Power BI, non di cosa parla |
| **Tipo**, **Approvazione** | **o** | compaiono solo se servono |

- I numeri accanto a ogni voce tengono conto degli altri filtri attivi: non si finisce su «nessun risultato».
- I filtri attivi sono elencati sopra i risultati, ognuno con la × per toglierlo.
- La colonna dei filtri **resta ferma** mentre scorri, se ci sta tutta nello schermo; altrimenti scorre con la pagina.
  Tag e workspace mostrano le prime 8 voci più «Mostra tutti». Il pulsante **«Nascondi»** la toglie per dare tutta
  la larghezza ai report; si riapre con «Filtri (n)» sopra l'elenco, e la scelta viene ricordata.
  Le sezioni chiuse indicano quante voci hai scelto, es. «Workspace (1)».
- **Schede o elenco**: i due pulsanti in alto a destra; la scelta viene ricordata.
- **Ordine**: per categoria (predefinito), per nome, dati più recenti; con una ricerca anche «più pertinenti».
- **Report con dati fermi da più di 3 mesi** finiscono in fondo a ogni gruppo, sotto una riga tratteggiata
  (soglia: `catalog.old_after_days`).
- **Pagine**: oltre `catalog.page_size` report (oggi 48) l'elenco va su più pagine, con un avviso che dice
  quanti ne mostra e in che ordine.
- **Risultati deboli**: i report trovati solo tra i nomi tecnici dei dati (una colonna «fatturazione», una tabella
  «t2viaggi clienti») stanno in una sezione chiusa in fondo, «Altri N report trovati solo tra i nomi tecnici dei dati»,
  che si apre con un clic (e da sola se i risultati pertinenti sono meno di 3). Lì finisce anche tutto ciò che viene da
  un modello condiviso da più report (es. il Master) quando Ramingo non sa quali campi usa ciascun report.
- **Errori di battitura**: se una parola non esiste nel catalogo, Ramingo cerca la parola più simile
  («carburnate» → «carburante») e lo dice sopra i risultati.
- **Suggerimenti mentre scrivi**: oltre ai report, categorie e tag il cui nome corrisponde
  (es. «Tag #benelux · 12 report»): un clic apre il catalogo già filtrato.

### 9.1.1 I tag

Servono per i temi che attraversano più categorie, per esempio «lavaggi»: report di Operations, di Contabilità
e della Direzione possono riguardare tutti i lavaggi.

- **Come si assegnano:** scheda del report → «Scrivi o correggi le descrizioni» → campo **Tag**, separati da virgola.
  Sotto il campo ci sono i tag **già usati**: cliccandoli si aggiungono. Così si riusa «lavaggi» invece di
  scrivere «lavaggio» o «lavaggi cisterne», che sarebbero tre tag diversi.
- **Come li trovano i colleghi:** il tag compare sulla scheda del report (#lavaggi) e cliccandolo si vedono tutti
  i report con quel tag. Nella pagina iniziale c'è il filtro **Tag**, che elenca tutti i tag con quanti report li usano.
  Anche la ricerca li considera: cercando «lavaggi» escono i report con quel tag, anche se la parola non è nel nome.
- **Quanti:** pochi. Se un tag finisce su quasi tutti i report di una categoria, è una categoria, non un tag.

### 9.1.2 Le ricerche senza risultati

**Amministrazione → Ricerche** mostra cosa cercano i colleghi e, soprattutto, cosa cercano **senza trovare niente**.
Ramingo registra solo il testo cercato e quanti report ha trovato, **non chi ha cercato**; i dati restano un anno.
Per ogni ricerca senza risultati la colonna «Oggi trova» rifà la ricerca: dopo aver aggiunto un tag, una descrizione
o un sinonimo si vede subito se basta.

### 9.1.3 Sinonimi di ricerca

**Amministrazione → Sinonimi di ricerca**: ogni riga è un gruppo di parole che la ricerca tratta come uguali
(«personale, dipendenti, hr, risorse umane»). La prima parola è quella mostrata come interpretazione.
La casella **Prova** mostra come Ramingo interpreta una ricerca. Dalle ricerche senza risultati c'è il collegamento
«aggiungi come sinonimo». Alla prima apertura la pagina parte dai gruppi di `config/synonyms.php`; da lì in poi
contano quelli salvati nella pagina. Le parole da ignorare («voglio», «report», articoli…) restano nel file.

Da non confondere con il **Glossario**: il glossario dà un nome comprensibile ai dati e lo vedono i colleghi;
i sinonimi lavorano solo dietro la ricerca.

### 9.2 Contenuti che Ramingo non mostra mai

Alcuni contenuti li crea Microsoft, non l'azienda, e restano fuori da catalogo e ricerca:

- il workspace **Admin monitoring** (report «Feature Usage and Adoption», «Purview Hub», «Content Sharing»,
  «OneLake Catalog – Governance for Admins»). Fabric lo crea da solo per gli amministratori;
- i report **«Report Usage Metrics Report»**, generati da Power BI quando qualcuno apre le metriche di utilizzo di un report.

Per escludere anche altri workspace (per esempio «TEST») si aggiunge il loro ID in `config/config.php` →
`sync.exclude_workspace_ids`.

---

## 10. Intelligenza artificiale

Facoltativa. Vedi [AI.md](AI.md). All'AI vanno solo nomi e descrizioni (mai i dati dei report) e tutto ciò che
produce è marcato «Interpretazione automatica».

**Se l'AI non funziona** (crediti finiti, chiave scaduta, niente internet):
- Ramingo non chiama mai l'AI da solo: solo quando qualcuno preme un pulsante («Chiedi all'AI», «Fatti spiegare…»).
  Catalogo, ricerca e schede funzionano uguale, e i testi scritti a mano o in Power BI restano sempre quelli mostrati.
- Chi preme il pulsante vede un messaggio chiaro: «crediti esauriti o pagamento non attivo», «chiave non valida»,
  «impossibile raggiungere il servizio AI (manca internet…)».
- Se la **chiave personale** non funziona e c'è una **chiave aziendale**, Ramingo riprova da solo con quella aziendale.
- Ogni tentativo, riuscito o no, è nel registro in Amministrazione.

---

## 11. Segreti e sicurezza, in breve

- Il segreto dell'app Ramingo-Sync si inserisce nel pannello ed è salvato **cifrato**; non viene più mostrato.
- L'app Ramingo-Sync ha permessi di **sola lettura** sui metadati di Power BI.
- Il segreto ha una scadenza (quella scelta in Azure, es. 12 mesi): **segnatela**. Alla scadenza la sincronizzazione
  fallisce; crea un nuovo segreto in Azure e incollalo nel pannello.

---

## 12. Problemi frequenti

| Sintomo | Causa probabile | Cosa fare |
|---|---|---|
| «Il segreto non è corretto» | copiato «ID segreto» invece di «Valore», o segreto scaduto | crea un nuovo segreto, copia la colonna **Valore** |
| «Power BI non autorizza ancora l'app» | impostazione tenant non applicata, app non nel gruppo, permessi Power BI aggiunti all'app | segui il messaggio del pannello; attendi 15 minuti |
| Nella scheda mancano tabelle e misure | impostazioni «metadati dettagliati» / «DAX e mashup» non attive | attivale nel portale di amministrazione, poi sincronizzazione completa |
| «Pagine non disponibili» | l'app non è membro del workspace | aggiungi Ramingo-Sync come *Visualizzatore* al workspace |
| Un report ha perso le descrizioni | è stato ripubblicato come oggetto nuovo (vedi §3) | riscrivi le descrizioni sul nuovo; in futuro usa «Sostituisci» |
| Incrementale «fallita» con `admin/workspaces/modified -> HTTP 400` | Power BI rifiuta la richiesta «solo i workspace modificati da…» (la data deve essere tra 30 giorni e 30 minuti fa, in UTC) | gestito da Ramingo: la data viene corretta e, se Power BI la rifiuta ancora, l'incrementale rilegge l'elenco completo invece di fallire. Il motivo esatto dato da Power BI finisce in `storage\logs` |
| Il riepilogo della sincronizzazione dice «pages/definitions_not_accessible» con un motivo «HTTP 401» o «HTTP 403» | Ramingo-Sync non è membro del workspace, oppure manca l'impostazione del tenant «Le entità servizio possono usare le API di Fabric» | aggiungilo ai workspace (Visualizzatore; per leggere la struttura dei report probabilmente Collaboratore) e controlla l'impostazione. Ramingo riprova da solo dopo 6 ore (`sync.retry_denied_hours`) |
| Sulle schede non compare «Dati aggiornati» | Ramingo-Sync non è membro del workspace del modello semantico | aggiungilo come Visualizzatore a quel workspace; il motivo esatto è nei Dettagli tecnici del report |
| Errore «no such table» | database non aggiornato dopo un aggiornamento di Ramingo | `php bin\migrate.php` |
