# Ramingo

Catalogo navigabile dei report e delle dashboard Power BI aziendali: ricerca in linguaggio naturale,
schede descrittive, link diretti ai report originali. Ogni informazione indica la sua provenienza:
**Power BI**, **Definizione tecnica**, **Scritto a mano**, **Interpretazione automatica**.

- **Guida per chi gestisce Ramingo (da leggere per prima):** [docs/GUIDA.md](docs/GUIDA.md)
- Architettura: [docs/ARCHITETTURA.md](docs/ARCHITETTURA.md)
- Collegamento al tenant Power BI: [docs/SETUP_POWERBI.md](docs/SETUP_POWERBI.md)
- Intelligenza artificiale (chiave aziendale e personale): [docs/AI.md](docs/AI.md)

## Requisiti

XAMPP con PHP 8.2. Negli esempi XAMPP è in `C:\xampp`: sul PC di prova è in
`C:\Users\utente\Documents\xampp`, adattare i percorsi.
In `php\php.ini` devono essere attive (senza `;` davanti):

```
extension=curl
extension=mbstring
extension=pdo_sqlite
extension=zip
```

## Avvio rapido con dati di esempio

Ramingo parte in modalità `fixture`: usa `database/fixtures/powerbi.sample.json`, che ha la stessa
struttura delle risposte reali di Power BI. Si può provare tutto senza credenziali.

```
cd C:\Users\utente\Documents\Ramingo
copy config\config.example.php config\config.php
C:\xampp\php\php.exe bin\migrate.php --seed-areas
C:\xampp\php\php.exe bin\sync.php --mode=full
```

### Pubblicare con Apache (XAMPP)

In `C:\xampp\apache\conf\extra\httpd-ramingo.conf` (e `Include conf/extra/httpd-ramingo.conf` in fondo a `httpd.conf`):

```apache
Alias /ramingo "C:/Users/utente/Documents/Ramingo/public"
<Directory "C:/Users/utente/Documents/Ramingo/public">
    AllowOverride All
    Require all granted
</Directory>
```

In `config\config.php` impostare `'base_path' => '/ramingo'` (lo stesso valore va in `RewriteBase` dentro
`public\.htaccess`), riavviare Apache e aprire `http://localhost/ramingo` (sul PC di prova: `http://localhost:8080/ramingo`).
Verificare che `mod_rewrite` sia attivo in `httpd.conf`.

Importante: si pubblica **solo** `public/`. Le altre cartelle (`config`, `storage`, `src`) non devono essere raggiungibili dal browser.

Per l'uso in rete da parte dei colleghi: HTTPS su Apache (certificato aziendale), `'https' => true`,
`'auth' => ['mode' => 'entra', ...]` e, se possibile, la cartella del progetto in un percorso di servizio
(es. `C:\Apps\Ramingo`) invece che in Documenti.

## Collegarsi al tenant reale

Seguire [docs/SETUP_POWERBI.md](docs/SETUP_POWERBI.md), poi in `config\config.php` impostare `'mode' => 'live'` con tenant, client id e segreto.
Cancellare `storage\ramingo.sqlite*` per eliminare i dati di esempio e rieseguire `migrate` e `sync --mode=full`.

## Sincronizzazione pianificata

Utilità di pianificazione di Windows → Crea attività:

| Attività | Programma | Argomenti | Frequenza |
|---|---|---|---|
| Ramingo completa | `C:\xampp\php\php.exe` | `C:\Users\utente\Documents\Ramingo\bin\sync.php --mode=full` | ogni notte |
| Ramingo incrementale | `C:\xampp\php\php.exe` | `C:\Users\utente\Documents\Ramingo\bin\sync.php --mode=incremental` | ogni ora |

L'esito di ogni sincronizzazione è visibile in **Amministrazione** e nei log in `storage\logs`.

## Comandi

| Comando | Cosa fa |
|---|---|
| `php bin\migrate.php [--seed-areas]` | crea/aggiorna il database (e aree di esempio) |
| `php bin\sync.php --mode=full\|incremental` | legge Power BI e aggiorna il catalogo |
| `php bin\reindex.php` | ricalcola interpretazioni automatiche e indice (dopo aver cambiato i sinonimi) |
| `php bin\generate-key.php` | genera la chiave di cifratura per le chiavi AI personali |
| `php bin\import-definition.php --report=<id> --path=<file.pbix\|cartella .Report>` | importa pagine e visual da una definizione tecnica |

## Migliorare la ricerca

La ricerca capisce frasi come «voglio vedere le vendite per regione» grazie al vocabolario aziendale in
`config\synonyms.php`: gruppi di parole equivalenti (italiano, inglese, gergo interno). Aggiungere i termini
usati in azienda è il modo più efficace per migliorarla; dopo la modifica eseguire `php bin\reindex.php`.

## Struttura

```
public/      unica cartella web (index.php, assets)
src/         codice PHP (Core, Domain, Models, Controllers, Services)
views/       template PHP
bin/         comandi da riga di comando
config/      configurazione e vocabolario
database/    schema e dati di esempio
storage/     database SQLite e log (non pubblico)
docs/        documentazione
```
