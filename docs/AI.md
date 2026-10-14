# Ramingo e l'intelligenza artificiale

## Cosa fa

| Funzione | Dove | Cosa produce |
|---|---|---|
| **Spiega con l'AI** | scheda di un report | descrizione proposta, «a chi serve», spiegazione di ogni misura |
| **Chiedi all'AI quale report usare** | pagina di ricerca | risposta breve e fino a 5 report consigliati, scelti **solo** fra quelli del catalogo |
| **Prova il collegamento** | La mia AI | verifica che chiave e modello funzionino |

Tutto ciò che l'AI produce è marcato **Interpretazione automatica**, con fornitore e modello
(es. `ai:anthropic:claude-sonnet-5-5`) e con l'utente che l'ha richiesto. Non sostituisce mai i testi scritti
a mano: nella scheda compaiono tutte le versioni, con priorità manuale > Power BI > AI > sintesi euristica.

## Cosa viene inviato al fornitore AI

Solo **metadati**: nome del report, workspace, descrizioni, pagine, visual, nomi di tabelle, colonne e misure.
Le formule DAX solo se `ai.send_expressions = true`. **Mai i dati contenuti nei report**: Ramingo non li possiede.
Prima di ogni invio l'interfaccia dice quale fornitore e quale chiave verranno usati.

Prima di attivare l'AI in azienda, verificare con IT/privacy quali fornitori sono ammessi.
L'opzione più prudente è **Azure OpenAI nel tenant aziendale** o un **modello locale** (Ollama, LM Studio).

## Due tipi di chiave

1. **Aziendale**: configurata dall'amministratore in `config/config.php` → `ai.company`. Vale per tutti.
2. **Personale**: ogni utente la imposta in **La mia AI**. Se presente, ha la precedenza per le sue richieste.
   - con login Entra: salvata nel database **cifrata** (libsodium) con `security.app_key`, mai mostrata di nuovo;
   - senza login: resta solo nella sessione del browser (senza identità non si può legare a una persona).
   - si può disattivare con `ai.allow_user_keys = false`.

Per le chiavi personali sono ammessi solo indirizzi HTTPS pubblici (protezione contro l'uso del server
Ramingo per raggiungere sistemi interni). La chiave aziendale può invece puntare a un modello locale (`http://localhost:11434/v1`).

## Fornitori supportati

| `provider` | Servizio | `model` | `endpoint` |
|---|---|---|---|
| `anthropic` | Anthropic Claude | es. `claude-sonnet-5-5` | fisso |
| `openai` | OpenAI | nome del modello | fisso |
| `azure` | Azure OpenAI | **nome del deployment** | `https://<risorsa>.openai.azure.com` |
| `compatible` | Mistral, Google Gemini, Ollama, LM Studio e altri compatibili OpenAI | nome del modello | es. `https://api.mistral.ai/v1`, `https://generativelanguage.googleapis.com/v1beta/openai`, `http://localhost:11434/v1` |

Microsoft Copilot (M365) non offre una chiave API da inserire: l'equivalente aziendale è Azure OpenAI.

## Attivazione

1. `config/config.php` → sezione `ai` (già presente, `enabled => true`).
2. Per la chiave aziendale compilare `ai.company`.
3. Per salvare chiavi personali con login Entra:
   - in `php.ini` togliere il `;` davanti a `extension=sodium` e riavviare Apache;
   - `php bin\generate-key.php` → copiare il valore in `security.app_key`.
4. `php bin\migrate.php` (crea le tabelle `user_ai_settings` e `ai_requests`).

## Controllo

- Ogni richiesta è registrata in `ai_requests` (utente, fornitore, modello, scopo, esito) e visibile in Amministrazione.
- Limite per utente: `ai.max_requests_per_hour`.
- Il testo inviato all'AI dichiara esplicitamente che i contenuti dei report sono materiale da analizzare e non
  istruzioni, e l'AI può citare solo report presenti nell'elenco fornito.
