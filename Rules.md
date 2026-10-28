# BLITTO PATENT DATABASE — Development Rules

> **Stack (2026):** Laravel 13, PHP 8.3, Blade + Tailwind 4, SQLite + Eloquent,
> Pest 4, Pint. Everything lives in `backend/`. The deep-module rules below are
> the ones that still govern the architecture; the tooling is now PHP, not the
> Java/Gradle intent in the git history.

## Architecture Enforcement

- Deep modules live under `backend/app/Domain/`: `ApplicationIntake`,
  `Lifecycle`, `Docketing`, `Prosecution`, `Filing`, `Vault`, `Notification`,
  `Authorization`, `Audit`, `Analytics`.
- No shallow wrappers — the deletion test must pass for any new module (delete
  it: does the caller become simpler? then it was a wrapper).
- Every seam has exactly two adapters: a production one and a test one
  (`DocumentStore` → `LocalDocumentStore` / `Storage::fake('vault')`;
  `MailChannel` → `LaravelMailChannel` / `ArrayMailChannel`).
- Controllers are thin: validate, delegate to a domain service, respond. No
  business rules in controllers.
- Eloquent models are persistence-only. No business logic in models.
- No circular dependencies between modules.

## Code Style (PHP 8.3 + Laravel 13)

- Domain vocabulary from `CONTEXT.md` — use "Application", "Deadline",
  "Document", "Inventor", "Office Action". Avoid "Entity", "DTO", "Manager".
- Constructor property promotion, explicit parameter and return types, curly
  braces always, PHPDoc array shapes, TitleCase enum case names.
- Adapters are named for what they adapt to (`LocalDocumentStore`,
  `ArrayMailChannel`), not `XxxAdapterImpl`.
- Format with `vendor/bin/pint` (Laravel preset); never hand-format around it.

## Testing Rules

- TDD always: write the failing test first, watch it fail, write the minimal
  code to pass, then refactor.
- Tests exercise public interfaces — HTTP routes and domain service methods.
  Never assert on private internals.
- Test seams with their test adapter (`Storage::fake('vault')`, `ArrayMailChannel`);
  never hit the real filesystem or mailer.
- One behaviour per test, clear name, real code (mocks only when unavoidable).
- Delete shallow-wrapper tests when a module is deepened.

## Git + CI Gates (required on every PR)

- `php artisan test --compact` passes (Pest suite).
- `vendor/bin/pint --test` passes.
- `composer validate --no-check-publish` passes.
- `php artisan dusk` passes in CI (Chrome provisioned by the workflow).
- An ADR is created for any new seam or module.
- Conventional commits (`feat(scope): …`, `fix(scope): …`, `docs(scope): …`,
  `test(scope): …`, `chore(scope): …`).

## Build and Run

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Documentation Standards

- `CONTEXT.md` is the first reference for domain terminology.
- ADRs in `docs/adr/` — one file per decision, numbered, dated.
- Layer plans and specs in `docs/superpowers/` are dated history; do not rewrite
  them to match later decisions.
- README and architecture docs (`Agent.md`, this file) describe the current
  system only.

## Coding Standards

- Prefer framework conventions over custom machinery: Eloquent relationships,
  form requests/validation, policies/gates, artisan commands, scheduler.
- No new Composer or npm dependency without approval.
- Keep configuration in `backend/config/*` and `BLITTODB_*` env keys.
- Errors return the L2 contract `{"message": …}` (plus `errors` for validation);
  HTML requests get the Blade error pages.
