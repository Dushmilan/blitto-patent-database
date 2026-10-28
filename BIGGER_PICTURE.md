# BLITTO PATENT DATABASE — Bigger Picture (context snapshot)

> Date: 2026-10-09 · Updated: 2026-10-28 · Status: current north star for all work.
> Supersedes the Approach-A / test-porting sections of
> `docs/superpowers/specs/2026-10-09-blitto-patent-database-laravel-migration-design.md`
> (§3 Approach, §9 test porting, §12 porting/dual-run risks). Everything else in
> that spec (architecture, schema, security, rename map) still applies.
> Tracking issue: [#65](https://github.com/Dushmilan/BLITTO--MANAGEMENT/issues/65)
> (closed at L7).

## 1. What we are building

A patent-application management platform for **BLITTO** (Business Linkage
Office, University of Peradeniya) that tracks applications filed with
**NIPO** (National Intellectual Property Office of Sri Lanka).

- Inventors submit patents physically; the system tracks them through the NIPO
  examination pipeline.
- Inventors get read-only access (own applications only) + email notifications
  on status changes; they can download granted patents.
- 3 roles: **MD** (everything incl. user management), **Director** (everything
  except user management), **User** (own applications only).

## 2. Domain core (read `CONTEXT.md` for the full glossary)

- **Lifecycle:** `DRAFT → FILED → {PUBLISHED, ACKNOWLEDGED, EXAMINATION,
  DEFECT_SHEET_1..3, REJECTED} → GRANTED → MAINTENANCE` (illegal transitions
  rejected).
- **6 invariants:** deadline integrity (missed = abandoned), role separation,
  document immutability after filing, docketing is the single source of truth
  for deadlines, audit trail on every state change, confidentiality (users see
  only their own).
- **4 workflows:** intake → prosecution (office actions auto-docket RESPONSE
  deadlines) → grant & maintenance → reporting.
- **Modular monolith:** deep modules (`ApplicationIntake`, `Lifecycle`,
  `Docketing`, `Prosecution`, `Filing`, `Vault`, `Notification`, `Authorization`,
  `Audit`, `Analytics`) with seams/adapters — discipline from `Rules.md`.

## 3. Current state (what exists today)

- **Backend:** Laravel 13 / PHP 8.3 in `backend/` — thin controllers, deep
  modules under `app/Domain/*`, SQLite + Eloquent persistence with the 12-table
  schema, session auth, and the document vault on the private `vault` disk.
- **UI:** Blade + Tailwind in `backend/resources/views` — login, staff
  dashboard/patents/users, and the inventor portal (overview, patents,
  notifications). State is server-rendered; no SPA (ADR-0006).
- **Tests:** Pest feature/domain suite in `backend/tests` plus the Dusk browser
  smoke suite in `backend/tests/Browser`; CI runs both jobs. Architecture graph
  via graphify (`graphify-out/`).
- **Reference implementation:** the FastAPI + React tree was deleted at L7. It
  survives at git tag `pre-laravel-final-state` and in the dated plans and specs
  under `docs/superpowers/`.
- **Problems the rewrite fixed:** #51 (state survives restarts), #50 (auth
  hardening), #49 (plaintext documents recorded as an accepted deferral in
  ADR-0004), role-name mismatch (gone with the SPA), and two-process deployment
  (one Laravel app now).

## 4. The decision (2026-10-09): complete rewrite, not migration

We built a **new Laravel application from scratch** and deleted the Python/React
code at cutover. We did **not** port the pytest suite.

Why not a layer-by-layer migration:

- Porting 310 tests would have been the bulk of the effort, and a port inherits
  the old design's quirks and "port-shaped" code.
- Dual-run drift: while both apps lived, every shared-behavior fix landed twice.
- The tests encoded the design we left, not necessarily the one we want.

What the old code/tests gave us (reference material, **never a gate**):

- A behavioral checklist and edge-case catalog while writing fresh Pest tests
  (throttling numbers, RBAC matrix, journey flows J1–J6, vault rules).
- Field lists for the 12-table schema.
- A document-crypto design to defer deliberately (ADR-0004).
- Seed data: `dev-data/seed_snapshot.json` (plaintext demo passwords are
  bcrypt'd on import).

What survived untouched: the domain (`CONTEXT.md`), design tokens
(`DESIGN.md`), the layer sequencing, and the issue tracker.

## 5. Architecture

- **Stack:** Laravel 13, PHP 8.3+, Blade + Tailwind (`DESIGN.md` tokens),
  SQLite + Eloquent, Laravel session auth, Pest 4 + Dusk.
- **Location:** one application in `backend/`; app name **BLITTO PATENT
  DATABASE**; env prefix **`BLITTODB_`** (renamed from `BLITTO_*`).
- **Schema:** 12 tables designed at L0 — `users`, `applications`, `inventors`,
  `status_history`, `deadlines`, `office_actions`, `responses`, `documents`,
  `filing_records`, `defect_sheets`, `notifications`, `audit_logs` —
  FK-constrained and indexed.
- **Structure:** controllers stay thin; domain logic in `app/Domain/*`
  (deep-module layout per `Rules.md`); Eloquent models are persistence-only.
- **Deployment:** a single PHP process (dev server on `:8000`, PHP-FPM in
  production) with SQLite and the private vault disk. No second application.

## 6. Pipeline (each layer green before the next starts — checklist on #65)

| Layer | Issue | Deliverable | New tests (TDD) |
|---|---|---|---|
| L0 Foundation | #68 | PHP 8.3 + Composer (installed system-wide; CI uses `shivammathur/setup-php`), Laravel 13 skeleton, `BLITTODB_` prefix, SQLite, full 12-table schema, `backend-laravel` CI job | scaffold + health check |
| L1 Domain | #73 | Eloquent models, lifecycle transition table, `app/Domain/*`, 6 invariants | lifecycle/model |
| L2 Auth & RBAC | #72 | Session auth, 3 roles, gates from RBAC matrix, email-domain policy, throttling, bootstrap admin | auth + RBAC + security |
| L3 Core flows | #67 | Applications CRUD, history, status changes, docketing, office actions, filing workflow | applications/docketing/prosecution/filing feature tests + RBAC matrix (document journeys J1/J2/J4 land in L4) |
| L4 Document vault | #71 | Plaintext document store seam (`DocumentStore`/`local`), upload rules, staff upload/list/download/delete, inventor granted download (file or ZIP) + request-document, missing-documents worklist, grant-without-document warning. Encryption and the vault unlock gate are deferred — ADR 0004. | document upload/download/request feature tests + journeys J1/J2/J4 |
| L5 Cross-cutting | #69 | Mail seam (`MailChannel` + Laravel-backed and in-memory adapters) with best-effort delivery, audit write gaps closed (filing IP, `create_user`, `auth:login|logout`), `GET /analytics/portfolio` + `/analytics/deadlines`, `nipo:follow-up` scheduled task, snapshot-driven idempotent demo seeder. `by_type` counts OPEN deadlines only and the reminder is deliberately not deduplicated — ADR 0005. | notification/analytics/seed |
| L6 UI | #70 | Blade screens (no Livewire — ADR 0006) replacing all React pages; server-rendered state, DESIGN.md tokens, dark mode; list-only notifications read API; Dusk smoke + Chrome in CI | Pest feature + Dusk smoke |
| L7 Cutover ✅ | #66 | Python/React tree deleted (reference at tag `pre-laravel-final-state`), living docs rewritten for Laravel, package renamed, repository + folder renamed to `blitto-patent-database` | full Pest suite + Dusk smoke green |

## 7. Rules of engagement

- **TDD always** (`AGENTS.md`): red-green-refactor, one test at a time, tests
  through public interfaces, never refactor while red.
- **graphify:** `graphify query "<question>"` for codebase questions;
  `graphify update .` after modifying code (AST-only, no API cost).
- **Deep modules:** tests at module/public-interface level only; every seam has
  a production + test adapter; no business logic in controllers or Eloquent
  models.
- **Docs:** `CONTEXT.md` first for terminology; ADRs in `docs/adr/` for new
  seams/modules.

## 8. Where to look for what

| Need | Source |
|---|---|
| Domain terms, invariants, workflows | `CONTEXT.md` |
| Requirements/edge cases to test | `backend/tests/Feature/` (Pest) |
| Module behaviour and seams | `backend/app/Domain/*` |
| RBAC matrix | `backend/tests/Feature/RbacMatrixTest.php`, `L3RbacMatrixTest.php`, `L4RbacMatrixTest.php` |
| UI behaviour + styling | `backend/resources/views/`, `DESIGN.md` |
| Seed/demo data | `dev-data/seed_snapshot.json`, `backend/database/seeders/DemoDataSeeder.php` |
| Configuration keys | `backend/.env.example`, `backend/config/blitto.php` |
| Design decisions | `docs/adr/0001`–`0006` |
| Historical reference implementation | tag `pre-laravel-final-state` |

## 9. Current status / next move

- Decision made, issues updated to the rewrite framing (2026-10-09).
- **Done: L0 (#68)** — Laravel 13 skeleton in `backend/`, `BLITTODB_` config
  prefix, SQLite + full 12-table schema, health-check Pest test,
  `backend-laravel` CI job. PHP 8.3.6 + Composer 2.7.1 installed
  system-wide (CI provisions its own via `shivammathur/setup-php`).
- **Done: L1 (#73)** — 12 persistence-only Eloquent models, lifecycle
  transition table, `app/Domain/*`, all 6 CONTEXT.md invariants tested
  (suite 131/131 green).
- **Done: L2 (#72)** — session auth (login/logout, guarded `APP_KEY`),
  3-role RBAC gates from the matrix, email-domain policy, throttling
  (failed logins per account + vault-unlock per IP), idempotent bootstrap
  admin. The L1 non-empty `reason` carry-over is resolved here; upload-flow
  immutability remains deferred to L4. Suite 194/194 green.
- **Done: L3 (#67)** — applications create/list/patch/history with own-only
  visibility, status changes (notify + audit), docketing (deadlines),
  office actions with auto-docketed response deadlines, and the filing
  workflow (file → acknowledge → defect sheets → grant/reject), plus the
  notification and audit seams. Document journeys J1/J2/J4 land in L4.
  Suite 298/298 green (739 assertions after Tasks 1–16).
- **Done: L4 (#71, reduced scope)** — plaintext document vault: the
  `DocumentStore` port with a `local` adapter on a private disk, upload
  extension/size/filename guards, staff upload/list/download/delete with
  filing immutability, the inventor granted download (single file or ZIP)
  with `request-document`, the missing-documents worklist and the
  grant-without-document warning. **No encryption and no vault unlock gate**
  — deferred and recorded in ADR 0004. Suite 346/346 green (997 assertions).
- **Done: L5 (#69)** — the cross-cutting layer: a `MailChannel` seam
  (`laravel` transport adapter + in-memory test adapter) that every
  notification now goes through, with the row persisted first and mail
  delivered best-effort; the audit write gaps closed (the five filing steps
  capture the client IP, and `create_user`, `auth:login`, `auth:logout` are
  recorded); the GRANTED-without-document defect fixed on the generic status
  route (the inventor is told the document is pending, the admin warned and the
  event audited — one shared `GrantWarning`); `GET /analytics/portfolio` and
  `GET /analytics/deadlines` for `director`/`md`; the `nipo:follow-up` command
  scheduled at `BLITTODB_NIPO_FOLLOW_UP_INTERVAL_HOURS`; and a
  snapshot-driven, idempotent demo seeder that bcrypts the reference plaintext
  passwords and writes document bytes through the store. Suite 399/399 green
  (1174 assertions). Decisions recorded in ADR 0005.
- **L6 UI** ✅ — Blade screens replace the React pages: login, role-aware `/`
  redirect, staff dashboard + patents (list, create, detail with status change,
  filing chain and documents) and users, inventor overview, patents (download,
  request) and notifications. JSON endpoints unchanged behind an
  `expectsJson()` branch; `EnsureRole` and the exception renderer negotiate HTML.
  `GET /notifications` read API added (list-only). DESIGN.md tokens + a dark
  palette defined in the spec (§7.3); no Livewire (ADR 0006); Dusk smoke suite in
  its own CI job. Suite 481/481 green (1452 assertions). Decisions: ADR 0006.
- **Done: L7 (#66)** — the reference Python/React tree is deleted (recoverable
  at tag `pre-laravel-final-state`), the living docs are rewritten for Laravel 13
  + Blade, the Composer package is `blitto/patent-database`, and the repository
  and local folder are renamed to `blitto-patent-database`. Pest suite green;
  Dusk smoke green in CI. Umbrella issue #65 closed.
- **Status: the layer pipeline is complete.** Future work is ordinary product
  iteration on top of the Laravel app (see the open issues), not another
  migration layer.
