# BLITTO PATENT DATABASE — backend

The Laravel 13 application (PHP 8.3, Blade + Tailwind, SQLite).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Tests

```bash
php artisan test --compact
vendor/bin/pint --test
php artisan dusk        # needs Chrome + chromedriver
```

## Where things live

- `app/Domain/*` — domain modules (business rules)
- `app/Http/Controllers` — thin controllers
- `app/Models` — persistence-only Eloquent models
- `database/migrations` — the 12-table schema
- `resources/views` — Blade screens
- `routes/web.php` — page + JSON routes
- `tests/Feature` — Pest feature suite; `tests/Browser` — Dusk smoke suite

Product documentation, roles, config and deployment notes are in the repository
root: [`../Readme.md`](../Readme.md), [`../CONTEXT.md`](../CONTEXT.md),
[`../BIGGER_PICTURE.md`](../BIGGER_PICTURE.md), and the working rules in
[`AGENTS.md`](AGENTS.md).
