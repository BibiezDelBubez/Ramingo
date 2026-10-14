# Collegare Ramingo a Power BI

Questa guida spiega **come Ramingo legge i metadati del tenant Power BI** e cosa va chiestoall'amministratore Microsoft 365 / Fabric. Ramingo **non legge mai i dati dei report** (fatturati, clienti, ecc.): legge solo *metadati* (nomi, autori, date, struttura dei modelli).

---

## 1. Come funziona in breve

```
 Utilità di pianificazione Windows
            │  (ogni ora / ogni notte)
            ▼
   php bin\sync.php ──► login.microsoftonline.com   (token del service principal "Ramingo-Sync")
            │
            ├──► API Admin "Scanner" (WorkspaceInfo)  → TUTTI i workspace, report, dashboard,
            │                                          modelli, tabelle, misure DAX, permessi
            ├──► API Reports "Get Pages"            → pagine dei report (dove ha accesso)
            ▼
     storage\ramingo.sqlite  ◄── la web app legge SOLO da qui
```

La web app non chiama Power BI quando un utente naviga: è veloce e non consuma quote API.
Il pulsante «Apri in Power BI» porta l'utente su app.powerbi.com, dove **Power BI applica i
suoi permessi reali**.

L'API usata per elencare tutto il tenant è la **Scanner API** (Admin → WorkspaceInfo):

- `GET  admin/workspaces/modified` → elenco dei workspace (tutti o modificati da una data)
- `POST admin/workspaces/getInfo?lineage=True&datasourceDetails=True&datasetSchema=True&datasetExpressions=True&getArtifactUsers=True` → avvia una scansione (max 100 workspace)
- `GET  admin/workspaces/scanStatus/{id}` → attende il completamento
- `GET  admin/workspaces/scanResult/{id}` → risultato JSON

Documentazione Microsoft: https://learn.microsoft.com/fabric/governance/metadata-scanning-overview

---

## 2. Cosa chiedere agli amministratori (una tantum)

### 2.1 Registrazione app "Ramingo-Sync" (amministratore Entra ID)

1. Portale Entra ID → **Registrazioni app** → **Nuova registrazione**
   - Nome: `Ramingo-Sync` · Account: *solo questa organizzazione* · nessun URI di reindirizzamento
2. Annotare **ID applicazione (client)** e **ID directory (tenant)**.
3. **Certificati e segreti** → nuovo segreto client (es. scadenza 12 mesi) → annotare il **valore**.
4. **Autorizzazioni API: NON aggiungere nessuna autorizzazione Power BI.**
   Per le API Admin, Microsoft richiede che il service principal *non* abbia permessi Power BI
   con consenso amministratore; i diritti arrivano dalle impostazioni del tenant (punto 2.3).
5. Creare un **gruppo di sicurezza** (es. `SG-Ramingo-ServicePrincipal`) e aggiungervi
   l'applicazione `Ramingo-Sync` come membro.

### 2.2 Registrazione app "Ramingo-Login" (facoltativa, per il login degli utenti)

Serve solo con `auth.mode = 'entra'` (consigliato quando Ramingo è in rete).

1. Nuova registrazione `Ramingo-Login`, piattaforma **Web**,
   URI di reindirizzamento: `https://<server-ramingo>/auth/callback`
   (Entra accetta `http://` solo per `localhost`: in rete serve HTTPS su Apache).
2. Segreto client → annotare il valore.
3. Autorizzazioni: solo `openid`, `profile`, `email` (delegate, nessun consenso admin necessario).
4. **Configurazione token** → *Aggiungi attestazione gruppi* → **Gruppi di sicurezza**.
   Serve a Ramingo per stimare a quali report l'utente ha accesso.

### 2.3 Impostazioni del tenant (amministratore Fabric / Power BI)

Portale di amministrazione Fabric → **Impostazioni tenant**. Abilitare **per il solo gruppo**
`SG-Ramingo-ServicePrincipal` (i nomi possono variare leggermente tra versioni del portale):

| Sezione                         | Impostazione                                                   | Perché                            |
| ------------------------------- | -------------------------------------------------------------- | --------------------------------- |
| Impostazioni API amministratore | *Service principals can access read-only admin APIs*           | elencare tutto il tenant          |
| Impostazioni API amministratore | *Enhance admin APIs responses with detailed metadata*          | tabelle, colonne, misure          |
| Impostazioni API amministratore | *Enhance admin APIs responses with DAX and mashup expressions* | formule DAX e Power Query         |
| Impostazioni sviluppatore       | *Service principals can use Fabric APIs*                       | pagine dei report (API non-admin) |

Le modifiche possono richiedere fino a 15 minuti per diventare effettive.

### 2.4 Pagine dei report (facoltativo)

L'elenco delle pagine di un report si ottiene con un'API **non** amministrativa, che richiede
che `Ramingo-Sync` sia almeno **Visualizzatore** del workspace. Senza questo accesso Ramingo
funziona comunque: mostra tutto tranne le pagine, e lo segnala nella scheda.
Opzioni: aggiungere il service principal come Visualizzatore ai workspace principali, oppure
importare le definizioni a mano (scheda del report → Dettagli tecnici → «Leggi dal file .pbix»).

Lo stesso accesso al workspace serve per leggere **quali campi usa ogni report** (vedi GUIDA §6.2):
Ramingo scarica il report senza dati (API *Export Report*, modalità LiveConnect) e ne legge la struttura.

---

## 3. Configurare Ramingo

**Dal pannello (consigliato):** Amministrazione → **Collegamento Power BI**. Si inseriscono tenant ID,
client ID e segreto (salvato cifrato), si preme **Prova collegamento**, che dice in italiano cosa non va,
e poi **Passa ai dati reali**, che cancella i dati di esempio e avvia la prima sincronizzazione.

**In alternativa, nel file** `config\config.php`; i valori compilati qui vincono su quelli del pannello:

```php
'powerbi' => [
    'mode'          => 'live',
    'tenant_id'     => '<ID directory>',
    'client_id'     => '<ID applicazione Ramingo-Sync>',
    'client_secret' => '<valore segreto>',
],
```

### Rete aziendale: proxy e certificati

Se il server esce su Internet tramite proxy: `'http' => ['proxy' => 'http://proxy:8080']`.

Se compare *"SSL certificate problem: unable to get local issuer certificate"*, PHP non trova
l'elenco delle autorità di certificazione. Con XAMPP di solito esiste già
`C:\xampp\apache\bin\curl-ca-bundle.crt`: indicarlo in `'http' => ['ca_bundle' => ...]`
(oppure in `php.ini` → `curl.cainfo`). Se il proxy aziendale ispeziona il traffico HTTPS,
aggiungere al bundle il certificato radice aziendale. **Non disattivare mai la verifica SSL.**

### Prima sincronizzazione

```
C:\xampp\php\php.exe bin\sync.php --mode=full
```

Errori tipici:

| Messaggio                             | Causa probabile                                                                |
| ------------------------------------- | ------------------------------------------------------------------------------ |
| `AADSTS7000215 invalid client secret` | segreto copiato male o scaduto                                                 |
| `HTTP 401` su `admin/...`             | impostazione tenant 2.3 non attiva o non ancora propagata, o SP non nel gruppo |
| `HTTP 403` su `admin/...`             | l'app ha permessi Power BI con consenso admin: rimuoverli (2.1 punto 4)        |
| tabelle/misure assenti                | impostazioni "detailed metadata" / "DAX expressions" non attive                |
| `HTTP 429`                            | limite API: Ramingo attende e riprova da solo                                  |

---

## 4. Limiti da conoscere

- La Scanner API accetta `modifiedSince` fino a 30 giorni indietro: oltre, Ramingo fa una scansione completa.
- Massimo 100 workspace per scansione e circa 500 richieste/ora: Ramingo procede a lotti.
- I **workspace personali** («Il mio workspace») sono esclusi per scelta (`sync.exclude_personal_workspaces`).
- La **data dell'ultimo refresh dei dati** richiede accesso al workspace (API Refresh History): prevista in una fase successiva.
- I **visual** di ogni pagina non sono esposti dalla Scanner API: si ottengono dalla definizione del
  report (`.pbix`, cartella PBIR, oppure in futuro l'API Fabric *Get Report Definition*).
