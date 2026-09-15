# Stranezze

Una piccola app locale per raccogliere osservazioni insolite della vita quotidiana. Il progetto è pensato per essere leggibile anche con basi iniziali di programmazione.

## Tecnologie

- HTML, CSS e JavaScript per l'interfaccia
- PHP per l'API locale
- SQLite e SQL per i dati
- Python per un report in sola lettura

## Requisiti Windows

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
.\start.ps1
```

Apri http://127.0.0.1:8000. Per fermare il server premi `Ctrl+C`.

Lo script `start.ps1` trova automaticamente la directory PHP installata e abilita `PDO_SQLITE`. Il database viene creato in `data/stranezze.sqlite`, fuori dalla cartella pubblica.

## Report Python

```powershell
python tools/report.py data/stranezze.sqlite
python tools/report.py data/stranezze.sqlite --json
```

Il report apre il database in sola lettura e non cambia i dati.

## Struttura

- `public/`: HTML, CSS e JavaScript
- `api/`: endpoint PHP JSON
- `database/`: schema SQL e inizializzazione
- `tools/`: strumenti locali Python
- `data/`: database runtime, escluso da Git
- `router.php`: serve i file pubblici e inoltra `/api`

## Scelte di sicurezza

L'app è intenzionalmente locale e non ha account, upload o chiamate a servizi esterni. L'API usa prepared statements, valida i dati sul server, limita categorie e lunghezze, restituisce JSON e non mostra dettagli degli errori interni. Il browser costruisce il contenuto con `textContent`, senza inserire HTML fornito dall'utente.

Queste misure riducono bug e vulnerabilita comuni, ma nessun software può garantire sicurezza assoluta. Prima di pubblicare dati reali, esegui aggiornamenti e test aggiuntivi.