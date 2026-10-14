-- =====================================================================
-- Ramingo - schema SQLite
-- Le tabelle sono raggruppate per PROVENIENZA del dato:
--   (1) pbi    -> metadati letti dalle API Power BI
--   (2) def    -> estratti da definizioni tecniche (modello, DAX, M, layout)
--   (3) manual -> scritti a mano in Ramingo
--   (4) auto   -> interpretazioni generate automaticamente
-- Le tabelle (1) e (2) vengono RISCRITTE dalla sincronizzazione.
-- Le tabelle (3) e (4) NON vengono mai toccate dalla sincronizzazione.
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------
-- (1) Metadati Power BI
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS workspaces (
    id                       TEXT PRIMARY KEY,
    name                     TEXT NOT NULL,
    description              TEXT,
    type                     TEXT,
    state                    TEXT,
    capacity_id              TEXT,
    is_on_dedicated_capacity INTEGER NOT NULL DEFAULT 0,
    raw_json                 TEXT,
    first_seen_at            TEXT NOT NULL,
    last_seen_at             TEXT NOT NULL,
    is_deleted               INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS datasets (
    id                    TEXT PRIMARY KEY,
    workspace_id          TEXT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    name                  TEXT NOT NULL,
    description           TEXT,
    configured_by         TEXT,
    created_at            TEXT,
    target_storage_mode   TEXT,
    content_provider_type TEXT,
    endorsement           TEXT,
    certified_by          TEXT,
    sensitivity_label_id  TEXT,
    raw_json              TEXT,
    first_seen_at         TEXT NOT NULL,
    last_seen_at          TEXT NOT NULL,
    is_deleted            INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_datasets_ws ON datasets(workspace_id);

CREATE TABLE IF NOT EXISTS reports (
    id                   TEXT PRIMARY KEY,
    workspace_id         TEXT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    dataset_id           TEXT,           -- può stare in un altro workspace: niente FK
    app_id               TEXT,           -- se il report è distribuito anche tramite app
    app_url              TEXT,           -- link "Apri nell'app"
    name                 TEXT NOT NULL,
    description          TEXT,
    report_type          TEXT,           -- PowerBIReport | PaginatedReport
    web_url              TEXT,
    created_by           TEXT,
    modified_by          TEXT,
    created_at           TEXT,
    modified_at          TEXT,
    endorsement          TEXT,           -- Certified | Promoted | NULL
    certified_by         TEXT,
    sensitivity_label_id TEXT,
    pages_fetched_at     TEXT,
    raw_json             TEXT,
    first_seen_at        TEXT NOT NULL,
    last_seen_at         TEXT NOT NULL,
    is_deleted           INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_reports_ws ON reports(workspace_id);
CREATE INDEX IF NOT EXISTS ix_reports_ds ON reports(dataset_id);

CREATE TABLE IF NOT EXISTS dashboards (
    id                   TEXT PRIMARY KEY,
    workspace_id         TEXT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    app_id               TEXT,
    app_url              TEXT,
    name                 TEXT NOT NULL,
    web_url              TEXT,
    is_read_only         INTEGER NOT NULL DEFAULT 0,
    sensitivity_label_id TEXT,
    raw_json             TEXT,
    first_seen_at        TEXT NOT NULL,
    last_seen_at         TEXT NOT NULL,
    is_deleted           INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS dashboard_tiles (
    dashboard_id TEXT NOT NULL REFERENCES dashboards(id) ON DELETE CASCADE,
    id           TEXT NOT NULL,
    title        TEXT,
    report_id    TEXT,
    dataset_id   TEXT,
    PRIMARY KEY (dashboard_id, id)
);

-- Chi ha accesso a cosa (Scanner API, opzione getArtifactUsers)
CREATE TABLE IF NOT EXISTS artifact_users (
    artifact_type  TEXT NOT NULL CHECK (artifact_type IN ('workspace','report','dashboard','dataset')),
    artifact_id    TEXT NOT NULL,
    graph_id       TEXT NOT NULL,        -- object id Entra dell'utente o del gruppo
    display_name   TEXT,
    email          TEXT,
    principal_type TEXT,                 -- User | Group | App
    access_right   TEXT NOT NULL,        -- Owner | Admin | Member | Contributor | Viewer | Read | ...
    PRIMARY KEY (artifact_type, artifact_id, graph_id, access_right)
);
CREATE INDEX IF NOT EXISTS ix_artifact_users_principal ON artifact_users(graph_id);

-- Pagine del report: 'pbi' se lette dall'API Pages, 'def' se dalla definizione del report
CREATE TABLE IF NOT EXISTS report_pages (
    report_id    TEXT NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    name         TEXT NOT NULL,
    display_name TEXT NOT NULL,
    ordinal      INTEGER NOT NULL DEFAULT 0,
    source       TEXT NOT NULL CHECK (source IN ('pbi','def')),
    PRIMARY KEY (report_id, name)
);

-- ---------------------------------------------------------------------
-- (2) Definizioni tecniche
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dataset_tables (
    id                INTEGER PRIMARY KEY,
    dataset_id        TEXT NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
    name              TEXT NOT NULL,
    description       TEXT,
    is_hidden         INTEGER NOT NULL DEFAULT 0,
    source_expression TEXT,              -- Power Query (M)
    UNIQUE (dataset_id, name)
);

CREATE TABLE IF NOT EXISTS dataset_columns (
    id          INTEGER PRIMARY KEY,
    table_id    INTEGER NOT NULL REFERENCES dataset_tables(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    data_type   TEXT,
    column_type TEXT,                    -- Data | Calculated | ...
    description TEXT,
    is_hidden   INTEGER NOT NULL DEFAULT 0,
    expression  TEXT                     -- DAX per colonne calcolate
);

CREATE TABLE IF NOT EXISTS dataset_measures (
    id            INTEGER PRIMARY KEY,
    table_id      INTEGER NOT NULL REFERENCES dataset_tables(id) ON DELETE CASCADE,
    name          TEXT NOT NULL,
    description   TEXT,
    is_hidden     INTEGER NOT NULL DEFAULT 0,
    expression    TEXT,                  -- DAX
    format_string TEXT
);

CREATE TABLE IF NOT EXISTS datasources (
    id                 INTEGER PRIMARY KEY,
    dataset_id         TEXT NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
    datasource_type    TEXT,
    connection_details TEXT,             -- JSON (server, database, url...)
    gateway_id         TEXT
);

CREATE TABLE IF NOT EXISTS report_visuals (
    id          INTEGER PRIMARY KEY,
    report_id   TEXT NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    page_name   TEXT NOT NULL,
    visual_type TEXT,
    title       TEXT,
    fields_json TEXT                     -- campi/misure usati dal visual
);
CREATE INDEX IF NOT EXISTS ix_visuals_report ON report_visuals(report_id, page_name);

-- ---------------------------------------------------------------------
-- (3) Scritto a mano  +  (4) Interpretazioni automatiche
-- Un'unica tabella versionata: la colonna source dice chi l'ha prodotto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS annotations (
    id          INTEGER PRIMARY KEY,
    entity_type TEXT NOT NULL CHECK (entity_type IN ('workspace','report','dashboard','dataset','measure')),
    entity_id   TEXT NOT NULL,
    field       TEXT NOT NULL,           -- description | business_owner | kpi | audience | notes | summary | ai_description | ai_audience
    value       TEXT NOT NULL,
    source      TEXT NOT NULL CHECK (source IN ('manual','auto')),
    author      TEXT,                    -- persona (manual) o "Ramingo" (auto)
    generator   TEXT,                    -- es. 'heuristic-v1' oppure 'ai:anthropic:<modello>'
    is_current  INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_annotations_entity ON annotations(entity_type, entity_id, field, source, is_current);

-- Tassonomia aziendale (manuale)
CREATE TABLE IF NOT EXISTS areas (
    id          INTEGER PRIMARY KEY,
    name        TEXT NOT NULL UNIQUE,
    slug        TEXT NOT NULL UNIQUE,
    description TEXT,
    icon        TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS workspace_areas (
    workspace_id TEXT PRIMARY KEY REFERENCES workspaces(id) ON DELETE CASCADE,
    area_id      INTEGER NOT NULL REFERENCES areas(id) ON DELETE CASCADE,
    assigned_by  TEXT,
    assigned_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tags (
    entity_type TEXT NOT NULL,
    entity_id   TEXT NOT NULL,
    tag         TEXT NOT NULL,
    source      TEXT NOT NULL CHECK (source IN ('manual','auto')),
    PRIMARY KEY (entity_type, entity_id, tag, source)
);

-- ---------------------------------------------------------------------
-- Ricerca (indice derivato, ricostruibile in ogni momento)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_docs (
    entity_type TEXT NOT NULL,
    entity_id   TEXT NOT NULL,
    title       TEXT NOT NULL,
    keywords    TEXT NOT NULL DEFAULT '',  -- area, workspace, tag, KPI
    body        TEXT NOT NULL DEFAULT '',  -- descrizioni, pagine, tabelle, misure
    PRIMARY KEY (entity_type, entity_id)
);
-- La tabella virtuale FTS5 "search_fts" viene creata da bin/migrate.php
-- solo se la build di SQLite la supporta; altrimenti si usa LIKE.

-- ---------------------------------------------------------------------
-- Servizio
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sync_runs (
    id          INTEGER PRIMARY KEY,
    mode        TEXT NOT NULL,             -- full | incremental
    started_at  TEXT NOT NULL,
    finished_at TEXT,
    status      TEXT NOT NULL,             -- running | ok | failed
    stats_json  TEXT,
    error       TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- ---------------------------------------------------------------------
-- Intelligenza artificiale
-- ---------------------------------------------------------------------
-- Chiave AI personale (solo con login Entra). La chiave è CIFRATA (libsodium).
CREATE TABLE IF NOT EXISTS user_ai_settings (
    user_id     TEXT PRIMARY KEY,         -- object id Entra
    provider    TEXT NOT NULL,
    model       TEXT NOT NULL,
    endpoint    TEXT,
    api_key_enc TEXT,                     -- mai in chiaro
    key_hint    TEXT,                     -- ultime 4 cifre, per riconoscerla
    updated_at  TEXT NOT NULL
);

-- Registro di ogni richiesta all'AI (chi, cosa, con quale fornitore): audit e limiti d'uso
CREATE TABLE IF NOT EXISTS ai_requests (
    id          INTEGER PRIMARY KEY,
    user_id     TEXT NOT NULL,
    key_owner   TEXT NOT NULL CHECK (key_owner IN ('company','personal')),
    provider    TEXT NOT NULL,
    model       TEXT NOT NULL,
    purpose     TEXT NOT NULL,            -- explain_report | ask_catalog | test
    entity_id   TEXT,
    ok          INTEGER NOT NULL,
    error       TEXT,
    created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_ai_requests_user ON ai_requests(user_id, created_at);

-- ---------------------------------------------------------------------
-- Categorie (3, manuale): albero + assegnazione al singolo report/dashboard
-- La colonna areas.parent_id viene aggiunta da bin/migrate.php sui database esistenti.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS item_areas (
    entity_type TEXT NOT NULL CHECK (entity_type IN ('report','dashboard')),
    entity_id   TEXT NOT NULL,
    area_id     INTEGER NOT NULL REFERENCES areas(id) ON DELETE CASCADE,
    assigned_by TEXT,
    assigned_at TEXT NOT NULL,
    PRIMARY KEY (entity_type, entity_id)
);

-- Categoria EFFETTIVA di ogni elemento: quella del singolo elemento, altrimenti quella del suo workspace.
-- Unico punto in cui è scritta questa regola: tutte le query la usano.
DROP VIEW IF EXISTS item_area;
CREATE VIEW item_area AS
    SELECT 'report' AS entity_type, r.id AS entity_id, COALESCE(ia.area_id, wa.area_id) AS area_id,
           CASE WHEN ia.area_id IS NOT NULL THEN 'item' WHEN wa.area_id IS NOT NULL THEN 'workspace' END AS assigned_on
      FROM reports r
 LEFT JOIN item_areas ia ON ia.entity_type = 'report' AND ia.entity_id = r.id
 LEFT JOIN workspace_areas wa ON wa.workspace_id = r.workspace_id
    UNION ALL
    SELECT 'dashboard', d.id, COALESCE(ia.area_id, wa.area_id),
           CASE WHEN ia.area_id IS NOT NULL THEN 'item' WHEN wa.area_id IS NOT NULL THEN 'workspace' END
      FROM dashboards d
 LEFT JOIN item_areas ia ON ia.entity_type = 'dashboard' AND ia.entity_id = d.id
 LEFT JOIN workspace_areas wa ON wa.workspace_id = d.workspace_id;

-- ---------------------------------------------------------------------
-- Glossario (3, manuale): significato umano dei nomi tecnici del modello.
-- Vale per NOME: la stessa tabella "VaGazTrrr" ha lo stesso significato in tutti i modelli.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS glossary (
    kind        TEXT NOT NULL CHECK (kind IN ('table','column','measure')),
    term        TEXT NOT NULL COLLATE NOCASE,   -- nome tecnico
    label       TEXT,                            -- nome comprensibile
    description TEXT,                            -- cosa contiene / cosa calcola
    updated_by  TEXT,
    updated_at  TEXT NOT NULL,
    PRIMARY KEY (kind, term)
);

-- Ultimo aggiornamento dei DATI di ogni modello semantico (refresh), letto da Power BI.
-- Senza vincolo verso datasets: il modello può stare in un workspace che Ramingo non cataloga.
CREATE TABLE IF NOT EXISTS dataset_refreshes (
    dataset_id          TEXT PRIMARY KEY,
    workspace_id        TEXT,
    last_success_at     TEXT,              -- fine dell'ultimo aggiornamento riuscito
    last_attempt_at     TEXT,              -- fine (o inizio) dell'ultimo tentativo
    last_attempt_status TEXT,              -- Completed | Failed | Unknown (in corso) | Disabled | Cancelled
    has_history         INTEGER NOT NULL DEFAULT 0, -- 0 = nessun aggiornamento registrato (es. DirectQuery)
    accessible          INTEGER NOT NULL DEFAULT 1, -- 0 = Ramingo-Sync non è membro del workspace del modello
    checked_at          TEXT NOT NULL
);
