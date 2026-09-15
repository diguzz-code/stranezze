# Stranezze

Una piccola app web per raccogliere osservazioni insolite della vita quotidiana. La v2 protegge i dati con login, sessione e CSRF e può essere pubblicata su un hosting PHP con SQLite.

## Tecnologie

- HTML, CSS e JavaScript per l'interfaccia
- PHP per l'API locale
- SQLite e SQL per i dati
- Python per un report in sola lettura
- Apache con rewrite per il deployment

## Configurazione sicura

La password non è scritta nel codice. Genera un hash con PHP:

```powershell
php -r "echo password_hash('scegli-una-password-lunga', PASSWORD_DEFAULT), PHP_EOL;"
```

Imposta il risultato come variabile d'ambiente `STRANEZZE_PASSWORD_HASH`. Su Windows PowerShell, solo per la sessione corrente:

```powershell
$env:STRANEZZE_PASSWORD_HASH = 'incolla-qui-l-hash'
```

Su un hosting, aggiungi la stessa variabile dal pannello di configurazione dell'applicazione. Non mettere la password, l'hash o un file `.env` reale in Git.

## Requisiti locali

Servono PHP 8.2 o superiore con `PDO_SQLITE`, Python 3.10 o superiore e Git.

Con winget:

```powershell
winget install --id Git.Git -e
winget install --id PHP.PHP.8.5 -e
```

Dopo l'installazione, apri un nuovo terminale.

## Avvio

Dalla cartella del progetto:

```powershell
$env:STRANEZZE_PASSWORD_HASH = 'il-tuo-hash'
.\start.ps1
```

Apri http://127.0.0.1:8000. Per fermare il server premi `Ctrl+C`.

Lo script `start.ps1` trova automaticamente la directory PHP installata, abilita `PDO_SQLITE` e inizializza il database. Il database viene creato in `data/stranezze.sqlite`, fuori dalla cartella pubblica.

## Funzioni v2

- Login con password hashata, cookie `HttpOnly` e `SameSite=Strict`
- Token CSRF per creazione e cancellazione
- Inserimento, ricerca, filtro per categoria e preferite
- Paginazione, statistiche e download CSV
- Validazione server-side e prepared statements
- Output del browser creato con `textContent`, senza HTML proveniente dai dati

## Pubblicazione su hosting PHP

Scegli un hosting con PHP 8.2+, `PDO_SQLITE`, SQLite scrivibile e Apache. Carica il progetto in una directory non pubblica oppure configura il document root sulla directory del progetto. Il file `.htaccess` blocca `data/`, `database/`, `api/`, `tools/`, `tests/` e i file di configurazione, inoltrando le richieste a `router.php`.

1. Carica i file del progetto, senza `data/stranezze.sqlite` se vuoi iniziare vuoto.
2. Crea la cartella `data` con permessi scrivibili dall'utente PHP.
3. Imposta `STRANEZZE_PASSWORD_HASH` nel pannello dell'hosting.
4. Esegui `php database/init.php` via SSH, oppure crea il database prima del deployment con l'ambiente PHP configurato.
5. Attiva HTTPS e usa l'URL pubblico dell'app.

L'app è pensata per un singolo proprietario. Per più utenti servirebbero una tabella account, password reset, ruoli e isolamento dei dati: non vanno aggiunti copiando questa autenticazione.

## Report Python

```powershell
python tools/report.py data/stranezze.sqlite
python tools/report.py data/stranezze.sqlite --json
```

Il report apre il database in sola lettura e non cambia i dati.

## Struttura

- `public/`: HTML, CSS e JavaScript
- `api/`: endpoint PHP JSON, autenticazione e configurazione
- `database/`: schema SQL e inizializzazione
- `tools/`: strumenti locali Python
- `data/`: database runtime, escluso da Git
- `router.php`: serve i file pubblici e inoltra `/api`
- `.htaccess`: protegge le directory private e abilita Apache rewrite

## Scelte di sicurezza

L'API usa prepared statements, valida i dati sul server, limita categorie e lunghezze, restituisce JSON e non mostra dettagli degli errori interni. Il browser costruisce il contenuto con `textContent`, senza inserire HTML fornito dall'utente. Le mutazioni richiedono sessione autenticata e CSRF.

Queste misure riducono bug e vulnerabilita comuni, ma nessun software può garantire sicurezza assoluta. Prima di pubblicare dati reali, esegui aggiornamenti e test aggiuntivi.