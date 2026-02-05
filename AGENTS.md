# Repository Guidelines

## Project Structure

- `src/`: PHP application code (`App\\`), e.g. controllers in `src/Controller/`, entities in `src/Entity/`.
- `config/`: Symfony configuration (packages, routes, services).
- `templates/`: Twig templates (base layout in `templates/base.html.twig`).
- `assets/`: Frontend assets managed by Webpack Encore (entrypoint `assets/app.js`), with Stimulus controllers under
  `assets/controllers/` and Turbo via Symfony UX.
- `public/`: Web entrypoint (`public/index.php`).
- `public/build/`: Webpack Encore build output (generated).
- `migrations/`: Doctrine migration classes.
- `tests/`: PHPUnit tests and bootstrap (`tests/bootstrap.php`).
- `docs/`: Project/design notes.
- `translations/`: Translation files.
- `var/`: Runtime cache/logs (local-only).

## Build, Test, and Development Commands

- `composer install`: Install PHP dependencies and run Symfony Flex auto-scripts.
- `npm install`: Install JS dependencies for Webpack Encore.
- `php bin/console`: Symfony CLI; useful subcommands include:
    - `php bin/console cache:clear`: Clear cache for the current `APP_ENV`.
    - `php bin/console doctrine:migrations:migrate`: Apply migrations.
- `npm run dev`: Build frontend assets (development).
- `npm run dev-server`: Run the Encore dev server.
- `npm run build`: Build frontend assets (production).
- `php -S localhost:8000 -t public`: Simple local web server (or use Symfony CLI if you have it installed).
- `docker compose up -d`: Start local Postgres (and Mailpit via `compose.override.yaml`); stop with
  `docker compose down`.

## Frontend (Bootstrap + Turbo)

- Bootstrap 5 is imported in `assets/app.js` and compiled by Webpack Encore.
- Default UI theme is dark-mode via `data-bs-theme="dark"` on `<html>` in `templates/base.html.twig`.
  To switch to light mode, change it to `data-bs-theme="light"` (or remove the attribute).
- `config/packages/webpack_encore.yaml` adds `data-turbo-track="reload"` to Encore tags so Turbo reloads assets on
  changes.

## Coding Style & Naming Conventions

- Follow `.editorconfig`: LF line endings, 4-space indentation (2 spaces for `compose*.yaml`), trim trailing whitespace.
- Keep Symfony conventions: `App\\` namespace, one class per file, clear service names, and Twig templates named
  `*.html.twig`.
- Always use the console commands to generate classes/migrations where possible and then edit them.

## Testing Guidelines

- Framework: PHPUnit (`php bin/phpunit`).
- Place tests under `tests/` and name files `*Test.php` (e.g., `tests/Service/FooTest.php`).
- Keep tests deterministic: use `APP_ENV=test` (set by `phpunit.dist.xml`) and avoid relying on dev data.

## Commit & Pull Request Guidelines

- Current history uses short, imperative subjects (e.g., `Initial Commit`); keep the first line concise and descriptive.
- PRs should include: what changed, how to test locally, and any migration/config steps. Include screenshots for UI
  changes.
- Do not commit secrets: use `.env.local` for developer-specific configuration; treat `.env*` as defaults only.

