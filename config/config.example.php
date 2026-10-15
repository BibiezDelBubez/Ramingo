<?php
/**
 * Copia questo file in config/config.php e personalizzalo.
 * config.php NON va condiviso né messo sotto versionamento: contiene segreti.
 * La cartella config/ è fuori dalla webroot (public/) e quindi non raggiungibile dal browser.
 */
return [
    'app' => [
        'name'      => 'Ramingo',
        'base_path' => '',            // es. '/ramingo' se pubblicata come sottocartella
        'timezone'  => 'Europe/Rome',
        'debug'     => false,         // true solo sul tuo PC, mai in rete
        'https'     => false,         // true quando Apache servirà Ramingo in HTTPS (consigliato)
    ],

    'database' => [
        'path' => 'storage/ramingo.sqlite',
    ],

    // Rete in uscita verso Microsoft (api.powerbi.com, login.microsoftonline.com)
    'http' => [
        'timeout'     => 60,
        'max_retries' => 3,
        'proxy'       => '',          // es. 'http://proxy.azienda.local:8080' se c'è un proxy
        'ca_bundle'   => '',          // es. 'C:/xampp/php/extras/ssl/cacert.pem' (vedi docs/SETUP_POWERBI.md)
    ],

    'powerbi' => [
        // Questi quattro valori si impostano da Amministrazione → Collegamento Power BI.
        // Se li compili qui, vincono su quelli del pannello (e nel pannello non sono modificabili).
        'mode'          => '',            // '' = deciso dal pannello | 'fixture' = dati di esempio | 'live' = tenant reale
        'tenant_id'     => '',
        'client_id'     => '',            // app registration "Ramingo-Sync"
        'client_secret' => '',
        'api_base'      => 'https://api.powerbi.com/v1.0/myorg',
        'web_base'      => 'https://app.powerbi.com',
        'authority'     => 'https://login.microsoftonline.com',
        'scope'         => 'https://analysis.windows.net/powerbi/api/.default',
    ],

    'sync' => [
        'exclude_personal_workspaces' => true,
        'exclude_workspace_ids'       => [],     // workspace da non catalogare mai
        'scan_batch_size'             => 100,    // massimo consentito dalla Scanner API
        'scan_poll_seconds'           => 5,
        'scan_timeout_seconds'        => 600,
        // Pagine dei report: richiede che il service principal sia membro dei workspace.
        // Se non lo è, Ramingo salta silenziosamente e lo segnala nel riepilogo.
        'fetch_report_pages'          => true,
        // Quali campi usa ogni report (scarica il report SENZA dati e ne legge la struttura).
        // Come le pagine, richiede che il service principal possa accedere al workspace.
        'fetch_report_definitions'    => true,
        // Se Power BI rifiuta pagine o struttura (permessi), si riprova dopo queste ore
        'retry_denied_hours'          => 6,
        // Data dell'ultimo aggiornamento dei DATI di ogni modello semantico (stesso requisito delle pagine).
        'fetch_data_refreshes'        => true,
        'refresh_check_minutes'       => 60,     // ogni modello si ricontrolla al massimo ogni N minuti
    ],

    'auth' => [
        // 'none'  = nessun login (solo per prove sul tuo PC)
        // 'entra' = login con l'account Microsoft aziendale (consigliato in rete)
        'mode'          => 'none',
        // App registration "Ramingo-Login" (web, delegata, solo openid/profile/email)
        'client_id'     => '',
        'client_secret' => '',
        'redirect_uri'  => 'https://ramingo.azienda.local/auth/callback',
        // Amministratori di Ramingo (gestione aree, descrizioni, stato sync)
        'admin_emails'  => [ 'admin@azienda.it' ],
        // Con mode = 'none' sono admin solo le richieste da questi IP
        'admin_ips'     => ['127.0.0.1', '::1'],
    ],

    'catalog' => [
        // Chi può vedere espressioni DAX / M: 'admins' | 'accessible' | 'all'
        'show_expressions'  => 'accessible',
        // true = nasconde dal catalogo i report a cui l'utente (probabilmente) non ha accesso
        'hide_inaccessible' => false,
        'page_size'         => 48,
        // Oltre questi giorni la data dei dati è evidenziata in ambra
        'stale_days'        => 7,
        // Ricerche proposte sotto la barra della pagina iniziale ([] = nessuna)
        'examples'          => ['vendite per regione', 'costi dei fornitori', 'consegne'],
    ],

    // Intelligenza artificiale (vedi docs/AI.md)
    'ai' => [
        'enabled'         => true,
        'allow_user_keys' => true,    // ogni utente può usare la propria chiave al posto di quella aziendale
        'send_expressions'=> false,   // true = invia anche le formule DAX al fornitore AI
        'max_requests_per_hour' => 30, // per utente
        // Chiave aziendale (lasciare 'provider' => '' per non averne)
        // provider: 'anthropic' | 'openai' | 'azure' | 'compatible'
        'company' => [
            'provider'    => '',
            'model'       => '',      // es. 'claude-sonnet-5-5'; per Azure: nome del deployment
            'endpoint'    => '',      // solo 'azure' e 'compatible' (es. https://risorsa.openai.azure.com)
            'api_key'     => '',
            'api_version' => '2024-10-21', // solo Azure
        ],
    ],

    // Chiave per cifrare le chiavi AI personali (64 caratteri esadecimali).
    // Generarla con:  php bin\generate-key.php   — se cambia, le chiavi personali vanno reinserite.
    'security' => [
        'app_key' => '',
    ],
];
