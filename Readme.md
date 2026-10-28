# BLITTO PATENT DATABASE

A patent-application management platform for **BLITTO** (Business Linkage Office,
University of Peradeniya) tracking applications filed with **NIPO** (National
Intellectual Property Office of Sri Lanka).

Built as a single Laravel 13 application in `backend/`: Blade + Tailwind UI,
SQLite/Eloquent persistence, session auth, Pest tests. The earlier FastAPI +
React implementation was replaced by this rewrite and survives only at the git
tag `pre-laravel-final-state`.

---

## Overview

Inventors submit patent disclosures physically through BLITTO. Staff record the
application, file it with NIPO, and track it through examination, grants and
maintenance. Inventors get read-only access to their own applications, status
notifications by email, and download access to granted patents.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3, in `backend/` |
| UI | Blade + Tailwind 4, `DESIGN.md` tokens, cookie-backed dark mode (ADR-0006) |
| Database | SQLite + Eloquent — 12 FK-constrained tables, created by migrations |
| Auth | Laravel session auth (`web` guard), institution email policy, roles `user` / `director` / `md` |
| Documents | `App\Domain\Vault\DocumentStore` with the `LocalDocumentStore` adapter on the private `vault` disk; plaintext at rest (ADR-0004) |
| Mail | `MailChannel` seam — Laravel mailer in production, in-memory adapter in tests (ADR-0005) |
| Analytics | `GET /analytics/portfolio`, `GET /analytics/deadlines` for staff |
| Scheduling | `nipo:follow-up`, scheduled from `BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS` |
| Architecture | deep modules under `backend/app/Domain/*`, decisions in `docs/adr/` |

## Roles

| Role | Capabilities |
|---|---|
| **MD** | Everything a Director can do, plus user management (create accounts, list users) |
| **Director** | Intake, docketing, office actions, filing workflow, document uploads, status changes, portfolio and deadline analytics |
| **User** | View own applications, review status, download granted patents, receive and read notifications |

Institution mail only: registration and login are gated on
`BLITTODB_ALLOWED_EMAIL_DOMAINS` (default `pdn.ac.lk`). Failed logins are
throttled per account.

## Application lifecycle

```
DRAFT → FILED → PUBLISHED / ACKNOWLEDGED / EXAMINATION / DEFECT_SHEET_1..3 / REJECTED
      → GRANTED → MAINTENANCE
```

Illegal transitions are rejected by `App\Domain\Lifecycle`. Office actions
(except `ALLOWANCE`) auto-docket a `RESPONSE` deadline through
`App\Domain\Docketing`, the single source of truth for deadlines.

## What the application does

- **Applications** — create, list, edit, read status history. Inventors see only
  their own applications; staff see the full docket.
- **Status changes** — every change is recorded in `status_history`, audited, and
  notifies the inventor by email.
- **Docketing** — deadline types, staff-only creation and listing, analytics on
  open/overdue work.
- **Prosecution** — office actions; a received action dockets its response
  deadline automatically.
- **Filing workflow** — file → acknowledge → defect sheets (1..3) → grant or
  reject. Granting without a document warns the caller and notifies the admin.
- **Documents** — staff upload, list, download and delete; the
  `missing-documents` worklist lists granted patents with no document. Deleting a
  document after filing returns `409`.
- **Granted-patent download** — the owning inventor downloads the granted patent
  as a single file or a ZIP of every document. A granted patent with no document
  returns `409` with `code=document_pending`; `POST /applications/{id}/request-document`
  asks staff to upload it (one open request per user).
- **Notifications** — email plus a read-only feed (`GET /notifications` and the
  inventor portal page).
- **Analytics** — portfolio counts and deadline workload for staff.
- **NIPO follow-up** — scheduled command that reminds admins about filings that
  have not been acknowledged.

## Setup

From `backend/`:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

`migrate --seed` restores the reference demo snapshot through
`DemoDataSeeder` (`dev-data/seed_snapshot.json`); seed passwords are bcrypt-hashed
on import.

## Run

```bash
cd backend
npm install
npm run build
php artisan serve          # http://127.0.0.1:8000
```

For UI work, `npm run dev` runs Vite alongside the PHP server. The page routes:
`/login`, staff `/admin/*`, inventor `/user/*`, plus the JSON endpoints under
`/applications`, `/deadlines`, `/analytics`, `/users`, `/notifications`,
`/health` and `/auth/*`.

## Tests

```bash
cd backend
php artisan test --compact     # Pest feature + domain suite
vendor/bin/pint --test         # style
php artisan dusk               # browser smoke (needs Chrome + chromedriver)
```

CI (`.github/workflows/test.yml`) runs the Pest suite and the Dusk smoke suite in
separate jobs.

## Configuration

Live defaults are in `backend/config/blitto.php`; `.env.example` lists the
overrides commented out so the suite runs against a clean environment.

| Variable | Default | Meaning |
|---|---|---|
| `BLITTODB_SERVICE_NAME` | `blitto-patent-database` | Service identifier |
| `BLITTODB_ENVIRONMENT` | `local` | Environment label |
| `BLITTODB_ALLOWED_EMAIL_DOMAINS` | `pdn.ac.lk` | Comma-separated institution domains allowed to register/log in |
| `BLITTODB_BOOTSTRAP_ADMIN_EMAIL` / `_PASSWORD` | empty | Optional first MD account created on boot |
| `BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS` | `24` | How often the NIPO follow-up checks for unacknowledged filings |
| `BLITTODB_DOCUMENT_STORE` | `local` | Document adapter; `local` is the only implementation, `pki` fails loudly |
| `BLITTODB_MAIL_CHANNEL` | `laravel` | Mail adapter: `laravel` (real mailer) or `array` (in-memory, tests) |
| `BLITTODB_DEMO_SNAPSHOT_PATH` | `../dev-data/seed_snapshot.json` | Snapshot restored by the demo seeder |

Password hashing, session and cache settings come from the standard Laravel
keys in `.env` (`APP_KEY`, `MAIL_*`, `DB_*`, …). The `APP_KEY` guard is enforced
outside `local`/`testing`.

## Document security (current state)

- Bytes are stored **plaintext** on the private `vault` disk
  (`backend/storage/app/vault`), never served directly by the web server.
  Encryption is deferred and recorded in **ADR-0004**; the `DocumentStore` port is
  the seam a future encrypted adapter plugs into.
- Uploads are allow-listed by extension — `.csv .doc .docx .jpeg .jpg .pdf .png .txt .xls .xlsx`
  — capped at 10 MiB and 255 filename characters.
- Reads and deletes go through `App\Domain\Vault\DocumentService`, which scopes a
  document to its application and refuses deletion once the application is filed.
- Granted-patent downloads require `GRANTED` plus ownership for the `user` role;
  each inventor download is audited.

## Deployment

One PHP host with PHP-FPM and a writable `backend/storage` tree:

- SQLite database file (`backend/database/database.sqlite`), backed up with the
  rest of the app.
- `backend/storage/app/vault` on a private volume (never under the document root).
- `php artisan migrate --force` on deploy.
- `php artisan schedule:run` from cron (or `php artisan schedule:work` under a
  supervisor) for the NIPO follow-up.
- `MAIL_MAILER` set to a real transport; notifications are persisted first and
  delivered best-effort.

## Architecture

- `backend/app/Domain/*` — deep modules: `ApplicationIntake`, `Lifecycle`,
  `Docketing`, `Prosecution`, `Filing`, `Vault`, `Notification`, `Authorization`,
  `Audit`, `Analytics`. Business rules live here, not in controllers or models.
- `backend/app/Http/Controllers` — thin controllers that validate, delegate and
  render/respond.
- `backend/app/Models` — persistence-only Eloquent models.
- `backend/app/Support` — presentation helpers (`Theme`, `Navigation`, `PatentTable`, …).
- Seams have a production and a test adapter: `DocumentStore`
  (`LocalDocumentStore` / `Storage::fake('vault')`), `MailChannel`
  (`LaravelMailChannel` / `ArrayMailChannel`).

## Documentation

| Document | Contents |
|---|---|
| `CONTEXT.md` | Domain glossary, invariants, roles, workflows |
| `BIGGER_PICTURE.md` | Why the rewrite happened and the L0–L7 roadmap |
| `DESIGN.md` | Design tokens and UI direction |
| `docs/adr/` | ADR-0001 … ADR-0006 — architecture decisions |
| `docs/superpowers/specs/` | Layer designs (L5, L6, migration) |
| `docs/superpowers/plans/` | Per-layer implementation plans |
| `backend/AGENTS.md` | Working rules for agents in this repository |
