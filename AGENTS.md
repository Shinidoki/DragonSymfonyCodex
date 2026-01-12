# Repository Guidelines

## Project Structure

- `src/`: PHP application code (`App\\`), e.g. controllers in `src/Controller/`, entities in `src/Entity/`.
- `config/`: Symfony configuration (packages, routes, services).
- `templates/`: Twig templates (base layout in `templates/base.html.twig`).
- `assets/`: Frontend assets managed by Symfony Asset Mapper/ImportMap (Stimulus controllers under
  `assets/controllers/`).
- `public/`: Web entrypoint (`public/index.php`).
- `migrations/`: Doctrine migration classes.
- `tests/`: PHPUnit tests and bootstrap (`tests/bootstrap.php`).
- `docs/`: Project/design notes.
- `translations/`: Translation files.
- `var/`: Runtime cache/logs (local-only).

## Build, Test, and Development Commands

- `composer install`: Install PHP dependencies and run Symfony Flex auto-scripts (cache clear, assets install, importmap
  install).
- `php bin/console`: Symfony CLI; useful subcommands include:
    - `php bin/console cache:clear`: Clear cache for the current `APP_ENV`.
    - `php bin/console doctrine:migrations:migrate`: Apply migrations.
    - `php bin/console importmap:install`: Install/update importmap-managed JS.
    - `php bin/console asset-map:compile`: Build asset mapper output for production.
- `php -S localhost:8000 -t public`: Simple local web server (or use Symfony CLI if you have it installed).
- `docker compose up -d`: Start local Postgres (and Mailpit via `compose.override.yaml`); stop with
  `docker compose down`.

## Coding Style & Naming Conventions

- Follow `.editorconfig`: LF line endings, 4-space indentation (2 spaces for `compose*.yaml`), trim trailing whitespace.
- Keep Symfony conventions: `App\\` namespace, one class per file, clear service names, and Twig templates named
  `*.html.twig`.

## Testing Guidelines

- Framework: PHPUnit (`php bin/phpunit`).
- Place tests under `tests/` and name files `*Test.php` (e.g., `tests/Service/FooTest.php`).
- Keep tests deterministic: use `APP_ENV=test` (set by `phpunit.dist.xml`) and avoid relying on dev data.

## Commit & Pull Request Guidelines

- Current history uses short, imperative subjects (e.g., `Initial Commit`); keep the first line concise and descriptive.
- PRs should include: what changed, how to test locally, and any migration/config steps. Include screenshots for UI
  changes.
- Do not commit secrets: use `.env.local` for developer-specific configuration; treat `.env*` as defaults only.

