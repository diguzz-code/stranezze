# Stranezze

[![CI](https://github.com/stranezze/app/actions/workflows/ci.yml/badge.svg)](https://github.com/stranezze/app/actions/workflows/ci.yml)

Una piccola app web per raccogliere osservazioni insolite della vita quotidiana. La v3 protegge i dati con login, sessione e CSRF e può essere pubblicata su un hosting PHP con SQLite.

## Tecnologie

- HTML, CSS e JavaScript per l'interfaccia
- PHP per l'API locale
- SQLite e SQL per i dati
- PHP per un report in sola lettura
- Apache con rewrite per il deployment

## Setup

Dopo il clone, dalla cartella del progetto installa le dipendenze e crea lo schema del database:

```powershell
composer install
composer migrate
```

Crea quindi il primo utente dalla CLI. La password viene trasformata in hash e non viene scritta nel codice o mostrata nell'output:

```powershell
php tools/create_user.php admin-user AdminPassw0rd! --role=admin
```

Avvia l'applicazione e apri http://127.0.0.1:8000 per accedere con l'utente appena creato:

```powershell
.\start.ps1
```

Per usare Docker Compose, copia `.env.example` in `.env.local` prima di avviare il container. Il file viene caricato direttamente da PHP tramite `vlucas/phpdotenv`; Docker Compose non interpreta né passa i suoi valori:

```powershell
Copy-Item .env.example .env.local
docker compose up --build
```

Non mettere password, hash o file `.env` o `.env.local` reali in Git.

## Configurazione ambiente

L'applicazione carica `.env.local` dalla directory principale del progetto tramite `Dotenv::createImmutable(...)->safeLoad()`. Il file è facoltativo e viene usato nello sviluppo locale e con Docker; `.env.local` resta escluso da Git.

In produzione non è necessario distribuire un file `.env`: è preferibile configurare le variabili dal pannello dell'hosting o dall'ambiente del processo PHP. Le variabili già presenti nell'ambiente del processo hanno priorità e non vengono sovrascritte da Dotenv. Se `.env.local` non esiste, l'applicazione continua a funzionare usando i valori dell'ambiente di sistema e i propri default.

Per verificare la configurazione locale:

```powershell
php -r 'require "api/config.php"; var_dump(getenv("APP_ENV"));'
```

I valori contenenti `$`, ad esempio `TEST_VAR=$2y$12$abc`, vengono letti da PHP senza l'interpolazione di Docker Compose.

## Requisiti locali

Servono PHP 8.2 o superiore con `PDO_SQLITE`, Composer e Git.

Con winget:

```powershell
winget install --id Git.Git -e
winget install --id PHP.PHP.8.5 -e
```

Dopo l'installazione, apri un nuovo terminale.

Il namespace `Stranezze\\` viene caricato da `src/` tramite autoload PSR-4. La cartella `vendor/` e i file `.env` restano esclusi da Git.

## Migration del database

Phinx gestisce lo schema SQLite tramite migration versionate. Per creare il database da zero:

```powershell
composer migrate
```

Per annullare l'ultima migration e ricreare lo schema:

```powershell
composer migrate:rollback
composer migrate
```

Il percorso predefinito è `data/stranezze.sqlite`; può essere sovrascritto con `STRANEZZE_DB_PATH`. Durante la transizione `database/init.php` resta disponibile, ma non va eseguito sullo stesso database già gestito da Phinx.

La migration `create_users_table` aggiunge la tabella `users` e il repository `Stranezze\\Infrastructure\\UserRepository` espone le operazioni per l'autenticazione multiutente. L'API autentica gli utenti tramite questa tabella, verifica `password_hash`, rifiuta gli utenti inattivi, salva `user_id`, `username` e `role` nella sessione e aggiorna `last_login_at`.

Per verificare il repository senza modificare i dati, dopo le migration esegui:

```powershell
php tools/test_user_repository.php
```

## Creazione utenti da CLI

Dopo il primo account, puoi creare altri utenti dal terminale passando username e password come argomenti. Il ruolo predefinito è `user`; lo script non richiede input interattivo.

Se `STRANEZZE_DB_PATH` non è impostata, viene usato `data/stranezze.sqlite`. Gli errori di validazione, inclusi username già esistente, password troppo corta e ruolo non valido, terminano con codice `1`; gli errori del database terminano con codice `2`.

## Avvio

Dalla cartella del progetto:

```powershell
.\start.ps1
```

Apri http://127.0.0.1:8000. Per fermare il server premi `Ctrl+C`.

Lo script `start.ps1` trova automaticamente la directory PHP installata, abilita `PDO_SQLITE` e inizializza il database. Il database viene creato in `data/stranezze.sqlite`, fuori dalla cartella pubblica.

## Funzioni v3

- Login con password hashata, cookie `HttpOnly` e `SameSite=Strict`
- Token CSRF per creazione e cancellazione
- Rate limiting del login: cinque fallimenti per IP o username in quindici minuti
- Header di sicurezza globali, con HSTS attivo solo su HTTPS
- Inserimento, ricerca, filtro per categoria e preferite
- Paginazione, statistiche e download CSV
- Validazione server-side e prepared statements
- Output del browser creato con `textContent`, senza HTML proveniente dai dati

## Testing

La suite automatica usa PHPUnit 11 e database SQLite temporanei: non modifica mai
`data/stranezze.sqlite`.

```powershell
composer test
composer test:unit
composer test:integration
composer test:coverage
```

La CI esegue `composer test` su PHP 8.2, 8.3 e 8.4 con SQLite e senza Docker.

## Dove trovare i log

Il logging strutturato è scritto da Monolog in `storage/logs/app-YYYY-MM-DD.log`. I file ruotano giornalmente e vengono conservati per trenta giorni. Sono registrati login riusciti o falliti, rate limit, logout, errori HTTP e database e richieste oltre 500 ms. I log non contengono password, hash, token CSRF o body delle richieste.

La directory `storage/logs/` contiene `.gitkeep`, mentre i file `.log` sono esclusi da Git. Se la directory non è scrivibile, il logging viene disattivato silenziosamente e l’applicazione non va in errore.

## Pubblicazione su hosting PHP

Scegli un hosting con PHP 8.2+, `PDO_SQLITE`, SQLite scrivibile e Apache. Carica il progetto in una directory non pubblica oppure configura il document root sulla directory del progetto. Il file `.htaccess` blocca `data/`, `database/`, `api/`, `tools/`, `tests/` e i file di configurazione, inoltrando le richieste a `router.php`.

1. Carica i file del progetto, senza `data/stranezze.sqlite` se vuoi iniziare vuoto.
2. Crea la cartella `data` con permessi scrivibili dall'utente PHP.
3. Crea l'utente amministratore con `php tools/create_user.php`.
4. Esegui `php database/init.php` via SSH, oppure crea il database prima del deployment con l'ambiente PHP configurato.
5. Attiva HTTPS e usa l'URL pubblico dell'app.

L'app è pensata per un singolo proprietario. Per più utenti servirebbero una tabella account, password reset, ruoli e isolamento dei dati: non vanno aggiunti copiando questa autenticazione.

## Report CLI

```powershell
php tools/report.php data/stranezze.sqlite
php tools/report.php data/stranezze.sqlite --json
```

Il report apre il database in sola lettura e non cambia i dati. Se il percorso non viene
specificato, usa `STRANEZZE_DB_PATH` oppure `data/stranezze.sqlite`.

## Struttura

- `public/`: HTML, CSS e JavaScript
- `src/`: codice PHP organizzato con namespace `Stranezze\\`
- `api/`: endpoint PHP JSON, autenticazione e configurazione
- `database/`: schema SQL e inizializzazione
- `tools/`: strumenti locali PHP
- `data/`: database runtime, escluso da Git
- `router.php`: serve i file pubblici e inoltra `/api`
- `.htaccess`: protegge le directory private e abilita Apache rewrite

## Scelte di sicurezza

L'API usa prepared statements, valida i dati sul server, limita categorie e lunghezze, restituisce JSON e non mostra dettagli degli errori interni. Il browser costruisce il contenuto con `textContent`, senza inserire HTML fornito dall'utente. Le mutazioni richiedono sessione autenticata e CSRF. Il login registra i tentativi nella tabella `login_attempts`; dopo cinque fallimenti nella finestra di quindici minuti restituisce HTTP 429. La CSP consente solo script, stili e immagini locali; per HSTS dietro reverse proxy, configurare `TRUSTED_PROXIES` con gli IP dei proxy autorizzati.

Queste misure riducono bug e vulnerabilita comuni, ma nessun software può garantire sicurezza assoluta. Prima di pubblicare dati reali, esegui aggiornamenti e test aggiuntivi.