# Ramingo — Architettura

Portale locale che rende esplorabile il patrimonio di report e dashboard del tenant Power BI.
Stack: **PHP 8.2 + JavaScript vanilla + SQLite** su XAMPP. Nessun framework, nessun Node, nessuna dipendenza esterna
(Bootstrap 5 e Font Awesome sono file locali in `public/assets`).

## 1. Principi

| Principio | Come è applicato |
|---|---|
| **Le 4 provenienze sono sempre distinte** | Ogni dato ha una `Source` (`pbi`, `def`, `manual`, `auto`) e a video un badge disegnato da un solo partial |
| **MVC** | `src/Models` (repository = accesso ai dati), `views/` (solo presentazione), `src/Controllers` (orchestrazione) |
| **DRY** | Un solo punto per: query (`Database`), upsert, URL Power BI (`PowerBiUrls`), badge (`partials/source_badge`), normalizzazione testo (`Text`), schede (`CardPresenter`) |
| **Sicuro per costruzione** | Webroot = `public/` soltanto; query preparate; escape `e()` in ogni vista; CSRF su ogni POST; CSP restrittiva; segreti fuori webroot; sync solo da riga di comando |
| **Power BI resta l'autorità** | Ramingo mostra metadati e linka: non fa da proxy, non mostra dati, non decide i permessi |
| **Sostituibile a pezzi** | Interfacce `PowerBiClient` (reale / esempio) e `InsightGenerator` (regole / futuro modello AI) |

## 2. Le quattro provenienze

| # | Source | Cosa contiene | Dove vive | Chi la scrive |
|---|---|---|---|---|
| 1 | `pbi` **Power BI** | nome, workspace, autori, date, certificazione, permessi, pagine | `workspaces`, `reports`, `dashboards`, `datasets`, `artifact_users`, `report_pages(source='pbi')` | sincronizzazione |
| 2 | `def` **Definizione tecnica** | tabelle, colonne, misure, DAX, Power Query, sorgenti, visual | `dataset_tables/columns/measures`, `datasources`, `report_visuals`, `report_pages(source='def')` | sincronizzazione / import definizione |
| 3 | `manual` **Scritto a mano** | descrizione, referente, pubblico, KPI, note, tag, categorie, glossario | `annotations(source='manual')`, `tags`, `areas` (albero), `workspace_areas`, `item_areas`, `glossary` | persone (admin e proprietari) |
| 4 | `auto` **Interpretazione automatica** | sintesi, KPI probabili, tag, interpretazione della ricerca, stima accesso | `annotations(source='auto')`, `tags(source='auto')`, calcolo al volo | `InsightService`, `QueryInterpreter`, `AccessService` |

Regole:
- la sincronizzazione **riscrive** (1) e (2) e **non tocca mai** (3) e (4);
- (3) e (4) sono **versionate** in `annotations` (`is_current`): nulla si perde;
- in caso di più versioni dello stesso campo la priorità di visualizzazione è **manuale > Power BI > automatica**,
  ma la scheda le mostra **tutte**, ognuna col suo badge.

## 3. Componenti

```
public/index.php            front controller + rotte + header di sicurezza
src/
  bootstrap.php             autoload, config, errori (comune web e CLI)
  Core/                     App (servizi), Router, Request, Controller, View, Database, Session
  Domain/                   Source (enum provenienza), Sourced (valore + provenienza)
  Models/                   repository: Catalog, Report, Dataset, Dashboard, Workspace,
                            ArtifactUser, Annotation, Tag, Area, Search, SyncRun
  Controllers/              Catalog, Search, Report, Dashboard, Annotation, Admin, Auth, Error
  Services/
    PowerBi/                PowerBiClient (interfaccia), RestPowerBiClient, FixturePowerBiClient,
                            TokenProvider, PowerBiUrls
    Sync/                   CatalogSync (orchestrazione), ScanResultImporter (JSON → tabelle)
    Definition/             ReportDefinitionParser (.pbix, PBIR, report.json → pagine e visual)
    Insight/                InsightGenerator (interfaccia), HeuristicInsightGenerator, InsightService
    Search/                 Text, QueryInterpreter, SearchIndexer, SearchService
    Auth/                   CurrentUser, EntraAuth (OIDC), AccessService (stima accesso)
    Ai/                     AiProvider (interfaccia), adattatori Anthropic e OpenAI-compatibili (OpenAI, Azure,
                            Mistral, Gemini, Ollama), AiConnections (chiave aziendale/personale), SecretBox
                            (cifratura), AiAssistant (spiega report, chiedi al catalogo)
    Catalog/                CardPresenter (prepara le schede per le viste)
    Http/                   HttpClient (cURL, proxy, retry 429)
views/                      layout + pagine + partials riusabili
bin/                        migrate, sync, reindex, import-definition (solo CLI)
config/                     config.php (segreti), synonyms.php (vocabolario aziendale)
database/                   schema.sql, fixtures/powerbi.sample.json
storage/                    ramingo.sqlite, log  (NON esposto dal web)
```

## 4. Flussi

**Sincronizzazione** (`bin/sync.php`, pianificata): elenco workspace → Scanner API a lotti da 100 →
`ScanResultImporter` (transazione per workspace; gli oggetti spariti vengono marcati `is_deleted`) →
pagine report → interpretazioni automatiche → ricostruzione indice di ricerca → riga in `sync_runs`.

**Categorie**: albero in `areas` (`parent_id`); assegnazione a workspace (`workspace_areas`) o al singolo elemento
(`item_areas`, che vince). La regola è scritta una sola volta nella vista SQL `item_area`, usata da tutte le query.

**Campi usati da ogni report**: `report_visuals.fields_json` (tabella, campo, tipo per ogni grafico), letto dalla
definizione del report (API Export in modalità LiveConnect, oppure .pbix caricato a mano) da `ReportDefinitionParser`
(formato classico e PBIR). `ReportUsage` filtra di conseguenza scheda, ricerca, proposte automatiche e AI:
indispensabile quando più report condividono lo stesso modello semantico.

**Glossario**: `glossary(kind, term)` dà nome e descrizione umani ai nomi tecnici, per nome e in tutti i modelli.
Lo usano scheda report («Dati e indicatori»), sintesi automatica, indice di ricerca e AI.

**Navigazione**: `CatalogController` → `CatalogRepository` (vista unificata report + impaginati + dashboard, filtri) →
`CardPresenter` (descrizione con provenienza, tag, stato accesso) → `views/catalog`.

**Ricerca intelligente**: «voglio vedere le vendite per regione» → `QueryInterpreter` elimina le parole vuote,
riconosce i concetti *vendite* e *regione* ed espande i sinonimi da `config/synonyms.php` →
`SearchRepository` (SQLite FTS5 con pesi titolo 10 / parole chiave 5 / contenuti 1; ripiego automatico su LIKE) →
prima i risultati con **tutti** i concetti, altrimenti **almeno uno** → per ogni risultato la spiegazione
«trovato perché: vendite nel titolo, regione nei contenuti». L'interpretazione è mostrata con badge AUTO.

L'indice include anche le colonne e le misure del modello: un report sulle vendite che ha una colonna *Regione*
viene trovato anche se il titolo non la nomina.

**Accesso**: con login Entra ID, `AccessService` incrocia l'utente e i suoi gruppi con i permessi letti dalla
Scanner API (diretti sul report o tramite workspace / app) → *Hai accesso* / *Probabilmente senza accesso* /
*Da verificare*. È una stima (badge AUTO nel tooltip): il link apre comunque Power BI, che decide.

## 5. Sicurezza

- Apache espone solo `public/`; `.htaccess` con `Require all denied` in tutte le altre cartelle come seconda difesa.
- Segreti solo in `config/config.php` (escluso da git); mai nei log.
- Service principal **in sola lettura** e solo su API admin di lettura; login utenti con soli scope `openid profile email`.
- PDO con prepared statements ovunque; `e()` su ogni output; CSRF su ogni POST; `SameSite=Lax`, `HttpOnly`, `Secure` (con HTTPS).
- CSP senza script o stili inline; `X-Frame-Options: DENY`; nessun redirect verso host esterni non previsti.
- Formule DAX/M visibili secondo `catalog.show_expressions` (default: solo a chi ha accesso al report).
- Opzione `catalog.hide_inaccessible` per nascondere del tutto i report non accessibili.
- La sincronizzazione gira solo da CLI (`bin/` rifiuta richieste web), con lock contro esecuzioni parallele.

## 6. Evoluzioni previste

| Fase | Contenuto |
|---|---|
| 1 (questo scheletro) | catalogo, filtri, ricerca con sinonimi, scheda report/dashboard, descrizioni manuali, aree, sintesi euristiche, login Entra |
| 2 | ultimo refresh dei dati; visual automatici via API Fabric *Get Report Definition*; statistiche d'uso (Admin *Activity Events*) per ordinare per popolarità |
| 3 | ✅ prima parte fatta: AI con chiave aziendale o personale (spiega report e misure, chiedi al catalogo) — vedi docs/AI.md. Prossimo: ricerca semantica |
| 4 | preferiti, «report simili», notifiche di modifica, lineage completo sorgente → modello → report |
