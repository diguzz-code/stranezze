# Progetto: Stranezze

## Cos'è
Web app per collezionare osservazioni insolite della vita quotidiana.
Stack: PHP 8.2+ / SQLite (WAL) / Apache + mod_rewrite / frontend ES modules vanilla.
Autenticazione multiutente, API versionata, security headers, rate limiting, logging strutturato, test automatici.

## Repo
https://github.com/diguzz-code/stranezze
Branch attivo: master
Stato: v3 completata (15 prompt di refactor + setup Docker)

## Architettura attuale

### Backend
- `api/index.php` — bootstrap sottile (~35 righe), carica config + auth + db + router
- `api/auth.php` — sessioni, CSRF, login/logout (usa UserRepository)
- `api/config.php` — carica autoload + Dotenv (.env.local)
- `api/db.php` — wrapper su DatabaseFactory
- `src/Domain/User.php` — entità immutabile readonly
- `src/Infrastructure/` — DatabaseFactory (WAL + PRAGMAs), UserRepository, RateLimiter, Logger, Phinx adapter custom
- `src/Http/` — Router (FastRoute), Routes, Request, Response, Middleware (Auth, SecurityHeaders), Controller (Auth, Observations, Stats, Export)

### Frontend
- `public/index.html` — pagina unica con login inline
- `public/js/api.js` — wrapper fetch (login, logout, session, CRUD, stats, export)
- `public/js/state.js` — stato condiviso (csrf, page, filtri)
- `public/js/ui.js` — rendering DOM, paginazione, messaggi
- `public/js/main.js` — bootstrap, listener, session check
- `public/styles.css` — stili correnti (font system-ui, CSP stretta)

### Database
- SQLite con WAL mode, busy_timeout 5000, synchronous NORMAL, foreign_keys ON
- Phinx per migrations versionate
- Tabelle: `observations`, `users`, `login_attempts`, `phinxlog`

### API
- `/api/v1/` — endpoint canonico (ufficiale)
- `/api/` — alias deprecato (backward compat con frontend attuale)
- Formato URL: `?action=login`, `?action=session`, `?page=1&per_page=10`, ecc.
- JSON in/out, status code standard

## Comandi utili

```bash
# Setup (Docker)
docker compose up --build

# Setup (PHP nativo)
composer install
composer migrate
php tools/create_user.php admin 'password' --role=admin

# Test
composer test            # tutta la suite
composer test:unit       # solo unit
composer test:integration # solo integration

# Migrations
composer migrate
composer migrate:rollback

# Report CLI
php tools/report.php data/stranezze.sqlite --json

## Convenzioni
- `declare(strict_types=1)` in ogni file PHP
- PSR-12
- Namespace root `Stranezze\`
- Readonly properties per entità dominio
- PDO iniettato via costruttore, MAI singleton
- Prepared statements sempre
- Frontend: no bundler, no framework, ES modules nativi
- CSP stretta (solo risorse self)
- No dipendenze esterne (font inclusi)

## Cosa NON toccare
- `api/auth.php`, `api/config.php`, `api/db.php` senza motivo
- `data/stranezze.sqlite`
- `.env.local` (gitignored)
- I PRAGMA in DatabaseFactory (WAL è critico)

## Cosa vogliamo migliorare (v3.5 / v4)

### Priorità alta
1. **Re-styling UX** — osare con l'estetica: design più moderno, animazioni, dark mode, layout responsive
2. **PWA + offline** — Service Worker, manifest, IndexedDB, sync
3. **Migrazione frontend a `/api/v1/`** — rimuovere dipendenza da alias deprecato
4. **Aggiungere test per controller HTTP** (parità `/api` vs `/api/v1`)
5. **Aggiungere test per middleware** (CSP, auth, rate limit)

### Priorità media
6. **Cleanup legacy** — rimuovere `start.ps1`, `php.ini`, `database/init.php`
7. **README v3 pulito** — rimuovere riferimenti v2, aggiornare struttura
8. **Migrazione a PHP 8.3+**
9. **Aggiungere PHPStan/Psalm**
10. **Aggiungere PHP-CS-Fixer**

### Priorità bassa
11. Multi-tenancy
12. Password reset
13. Ruoli granulari
14. Export PDF
15. Dark mode

## Contesto tecnico
- Node.js richiesto per DSH: installare `@deepseek-ai/dsh`
- Workspace WSL: usare plugin `dsh-wsl-workspace`
- Modalità consigliata: **Code Mode** (PTC)