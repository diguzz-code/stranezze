## 1. ARCHITETTURA ATTUALE

`router.php` distingue le richieste `api` da quelle statiche in `public`.
`.htaccess` imposta `router.php` come entry point, blocca alcune directory e inoltra tutte le richieste.
L’autenticazione è in `auth.php`, con credenziali da variabili d’ambiente definite in `config.php`.
Il login crea una sessione PHP, rigenera l’ID e genera un token CSRF.
Il login applica un rate limiting SQLite separato per IP e username: massimo cinque fallimenti in quindici minuti, con pulizia degli eventi oltre un’ora.
Le mutazioni API richiedono sessione autenticata e header `X-CSRF-Token`.
`api/index.php` è un bootstrap sottile: crea le dipendenze HTTP e delega il dispatch a `Stranezze\Http\Router`.
FastRoute abbina metodo e path `/api`; `Routes` risolve poi il parametro query `action` mantenendo invariati gli URL pubblici.
I controller in `src/Http/Controller/` contengono la logica HTTP e le query applicative, mentre `AuthMiddleware` applica autenticazione e CSRF in modo dichiarativo.
`Request` incapsula superglobali e body JSON; `Response` centralizza risposte JSON e CSV con terminazione immediata, preservando il contratto precedente.
`SecurityHeadersMiddleware` applica gli header di sicurezza alle risposte API e agli asset statici; HSTS viene inviato solo su HTTPS.
Il logging strutturato usa Monolog con un logger iniettabile e un file giornaliero rotante in `storage/logs/`, con retention di trenta giorni. Registra audit del login/logout, rate limit, errori HTTP, errori database e richieste oltre 500 ms senza password, hash, token CSRF o body HTTP.
Il database SQLite viene inizializzato da `init.php` e aperto tramite `DatabaseFactory`, usato da `db.php`, dai tool CLI e dall'adapter Phinx.
Il frontend è una pagina HTML unica con CSS e JavaScript vanilla.
`app.js` gestisce login, sessione, CRUD, ricerca, filtri, statistiche, paginazione ed export.

## 2. INVENTARIO FILE

| percorso | ruolo | righe stimate | dipendenze principali |
|---|---|---:|---|
| `router.php` | Routing tra API e file pubblici | 26 | PHP, `index.php`, `public` |
| `.htaccess` | Rewrite e blocco directory | 8 | Apache mod_rewrite |
| `.htaccess` | Blocco accesso diretto all’API | 1 | Apache |
| `auth.php` | Sessione, login, logout, CSRF | 55 | `config.php` |
| `config.php` | Configurazione e sessione sicura | 35 | Variabili d’ambiente, PHP sessioni |
| `db.php` | Connessione PDO a SQLite | 25 | PDO SQLite, database runtime |
| `DatabaseFactory.php` | Apertura e configurazione centralizzata delle connessioni SQLite | - | PDO SQLite |
| `index.php` | Bootstrap API e composizione dipendenze | 35 | `db.php`, `auth.php`, `src/Http` |
| `src/Http/Router.php` | Dispatch FastRoute e gestione errori globali | - | FastRoute, controller |
| `src/Http/Routes.php` | Route e action dichiarative | - | Request, Response |
| `src/Http/Request.php` | Wrapper della richiesta HTTP | - | Superglobali PHP |
| `src/Http/Response.php` | Risposte JSON e CSV | - | PHP HTTP |
| `src/Http/Middleware/AuthMiddleware.php` | Auth e CSRF per route protette | - | `auth.php` |
| `src/Http/Middleware/SecurityHeadersMiddleware.php` | Header di sicurezza HTTP globali | - | Superglobali HTTP |
| `src/Http/Controller/*` | Controller auth, osservazioni, stats, export | - | PDO, Request, Response |
| `src/Infrastructure/RateLimiter.php` | Rate limiting login su SQLite | - | PDO |
| `src/Infrastructure/Logger.php` | Wrapper Monolog per logging best-effort | - | Monolog, storage filesystem |
| `init.php` | Creazione database ed esecuzione schema | 31 | PDO SQLite, `schema.sql` |
| `index.html` | Struttura dell’interfaccia | 115 | `styles.css`, `app.js` |
| `app.js` | Logica frontend e chiamate API | 190 | Fetch API, DOM |
| `report.py` | Report read-only SQLite | 43 | Python, SQLite |
| `php.ini` | Configurazione PHP locale | 5 | PDO SQLite |
| `start.ps1` | Inizializzazione DB e avvio server | 19 | PHP, `php.ini`, `init.php` |
| `README.md` | Documentazione operativa e deployment | 85 | Tutti i componenti descritti |

## 3. ENDPOINT API ESISTENTI

| metodo | path | auth richiesta | CSRF | input | output | file |
|---|---|---|---|---|---|---|
| POST | `/api?action=login` | No | No | JSON `username`, `password` | `authenticated`, `csrf_token` | `index.php`, `auth.php` |
| POST | `/api?action=logout` | Sì | Sì | Header `X-CSRF-Token` | `authenticated: false` | `index.php`, `auth.php` |
| GET | `/api?action=session` | No | No | Nessuno | Stato autenticazione e token eventuale | `index.php` |
| GET | `/api?action=stats` | Sì | No | Nessuno | Totale, preferite, categorie | `index.php` |
| GET | `/api?action=export` | Sì | No | Nessuno | File CSV | `index.php` |
| GET | `api` | Sì | No | `q`, `category`, `favorite`, `page`, `per_page` | Lista osservazioni e paginazione | `index.php` |
| POST | `api` | Sì | Sì | JSON `title`, `content`, `category`, `observed_on`, `place`, `is_favorite` | ID della nuova osservazione | `index.php` |
| PUT | `/api?id={id}` | Sì | Sì | ID query string e JSON osservazione | `ok: true` | `index.php` |
| DELETE | `/api?id={id}` | Sì | Sì | ID query string | `ok: true` | `index.php` |

## 4. SCHEMA DATABASE ATTUALE

### Tabella `observations`

| colonna | tipo | vincoli | default |
|---|---|---|---|
| `id` | `INTEGER` | `PRIMARY KEY`, `AUTOINCREMENT` | Nessuno |
| `title` | `TEXT` | `NOT NULL`; `CHECK (length(title) BETWEEN 1 AND 80)` | Nessuno |
| `content` | `TEXT` | `NOT NULL`; `CHECK (length(content) BETWEEN 1 AND 500)` | Nessuno |
| `category` | `TEXT` | `NOT NULL`; `CHECK (category IN ('quotidiana', 'natura', 'persone', 'tecnologia', 'altro'))` | Nessuno |
| `observed_on` | `TEXT` | `NOT NULL` | Nessuno |
| `place` | `TEXT` | `NOT NULL`; `CHECK (length(place) <= 80)` | `''` |
| `is_favorite` | `INTEGER` | `NOT NULL`; `CHECK (is_favorite IN (0, 1))` | `0` |
| `created_at` | `TEXT` | `NOT NULL` | `CURRENT_TIMESTAMP` |
| `updated_at` | `TEXT` | `NOT NULL` | `CURRENT_TIMESTAMP` |

### Indici

| nome | tabella | colonne | ordinamento |
|---|---|---|---|
| `idx_observations_observed_on` | `observations` | `observed_on` | `DESC` |
| `idx_observations_category` | `observations` | `category` | Predefinito (`ASC`) |

### Foreign key

Non sono definite foreign key nello schema SQL.

### Tabella `login_attempts`

La tabella registra IP, username, timestamp e risultato (`success` 0/1). Gli indici su `(ip, attempted_at)` e `(username, attempted_at)` supportano il controllo della finestra mobile di quindici minuti. I fallimenti oltre cinque per uno dei due identificatori producono HTTP 429 con `Retry-After`; un login riuscito rimuove i fallimenti associati e viene conservato come evento di successo.

### Trigger

Non sono definiti trigger nello schema SQL.

### Logging

`Logger::create()` configura Monolog con `RotatingFileHandler`, creando `storage/logs/app-YYYY-MM-DD.log` e conservando trenta file giornalieri. Il formato è monolineare con timestamp ISO 8601, livello, canale, messaggio e contesto JSON. Se la directory non è scrivibile o una scrittura fallisce, viene usato un handler nullo e l’applicazione continua a rispondere normalmente.

### Inizializzazione tramite `init.php`

`init.php` calcola la directory radice, crea `data/` con permessi `0700` se non esiste e usa `data/stranezze.sqlite` come database SQLite. Apre il database tramite `DatabaseFactory`, che abilita le eccezioni, imposta `PDO::FETCH_ASSOC` e configura i PRAGMA comuni. Legge `database/schema.sql` con `file_get_contents()` e lo esegue integralmente tramite `$pdo->exec($schema)`. Se lo schema non viene letto, termina con errore; al termine stampa il percorso del database pronto. Le istruzioni `IF NOT EXISTS` rendono idempotente la creazione della tabella e degli indici già presenti.

### Connessioni SQLite e WAL

`src/Infrastructure/DatabaseFactory.php` centralizza la creazione e la configurazione delle connessioni PDO SQLite. Dopo l'apertura applica sempre, in questo ordine, `PRAGMA journal_mode = WAL`, `PRAGMA busy_timeout = 5000`, `PRAGMA synchronous = NORMAL` e `PRAGMA foreign_keys = ON`. L'adapter Phinx configura allo stesso modo la connessione creata dal proprio adapter.

L'impostazione dei PRAGMA a ogni nuova connessione è idempotente e non usa connessioni persistenti o pool. La modalità WAL consente letture concorrenti durante le scritture e crea, quando necessario, `data/stranezze.sqlite-wal` e `data/stranezze.sqlite-shm`; questi file sono runtime e sono esclusi da Git.

## 5. PUNTI DEBOLI CONCRETI

- `index.php`, funzione `requestData()`: non limita la dimensione del corpo HTTP prima di leggerlo integralmente con `file_get_contents('php://input')`.
- `index.php`, funzione `validateData()`: valida `category` solo in scrittura; il filtro GET `category` viene usato nella query senza verificare che appartenga alle categorie ammesse.
- `index.php`, ramo `GET` principale: `per_page` è limitato a 50 ma non esiste un limite esplicito alla lunghezza di `q`.
- `index.php`, funzione `respond()`: non gestisce il fallimento di `json_encode()`, quindi un payload non codificabile potrebbe produrre una risposta vuota o incompleta.
- `auth.php`, funzione `requireCsrf()`: il token CSRF è accettato esclusivamente tramite header, senza alternativa tramite corpo o parametro; questo vincola tutti i client a supportare tale header.
- `config.php`, funzione `startSecureSession()`: il cookie di sessione è marcato `secure` solo quando la richiesta corrente rileva HTTPS; su HTTP il cookie resta trasmissibile in chiaro.
- `index.php`, blocco `catch (Throwable $error)`: tutti gli errori applicativi vengono convertiti in HTTP 500 indistintamente, impedendo di distinguere errori di configurazione, database e input lato client.
- Il rate limiting del login richiede l’applicazione della migration `create_login_attempts`; senza la tabella il login restituisce errore interno fino all’allineamento dello schema.
- `app.js`, funzione `loadItems()`: un errore della richiesta principale non viene gestito internamente; il caricamento iniziale dipende dal `catch` di `boot()`, mentre i caricamenti successivi dai listener dei filtri gestiscono solo il messaggio del form.
- `app.js`, funzione `render()`: il contatore mostra solo il numero di elementi presenti nella pagina corrente, mentre l’API restituisce anche il totale filtrato; l’interfaccia può quindi mostrare un conteggio fuorviante.
- `.htaccess` e `.htaccess`: l’accesso diretto ad `api` è negato dal file Apache dedicato, mentre il routing applicativo richiede che `api` venga inoltrato a `index.php`; il comportamento dipende dalla corretta applicazione gerarchica delle regole Apache.