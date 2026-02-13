# DragonSymfony

Symfony 8 backend foundation for a persistent, simulated Dragon Ball–inspired RPG world (worlds, characters, time
advancement, and stat growth).

## Requirements

- PHP 8.4+
- Composer
- Node.js + npm (for Webpack Encore assets)
- A database configured via `DATABASE_URL` (default `.env` uses MariaDB/MySQL)

## Local setup

```bash
composer install
npm install
php bin/console doctrine:migrations:migrate --no-interaction
```

Build frontend assets:

```bash
npm run dev
```

Run a local server:

```bash
php -S localhost:8000 -t public
```

## Admin UI

Create an admin user:

```bash
php bin/console app:user:create --username=admin --admin
```

Then visit:

- `GET /login`
- `GET /admin`

## Frontend (Webpack Encore + Bootstrap)

- Entry point: `assets/app.js` (imports Bootstrap CSS/JS and starts Stimulus/Turbo).
- Twig includes Encore tags in `templates/base.html.twig`.
- Default theme is dark mode via `data-bs-theme="dark"` on `<html>` (change to `light` if desired).

Create a world:

```bash
php bin/console game:world:create --seed=earth-0001
```

Create a character:

```bash
php bin/console game:character:create --world=1 --name=Goku --race=saiyan
```

Generate a world map (tiles):

```bash
php bin/console game:world:generate-map --world=1 --width=32 --height=32 --planet=Earth
```

Set a character travel target:

```bash
php bin/console game:character:set-travel --character=1 --x=10 --y=10
```

Advance simulation (MVP: every character trains once per day):

```bash
php bin/console game:sim:advance --world=1 --days=7
```

If a character has a travel target, they travel one tile per day (X-first, then Y) instead of training until they
arrive.

## Overworld + combat model

Gameplay runs through the overworld simulation and combat resolution flow.

- Characters move and progress through world simulation commands.
- Fights resolve in turn-based RPG combat encounters (no separate local tactical runtime).
- Targeting model is basic: single-target and AoE only.
- Events and combat outcomes are emitted as part of simulation/combat processing.
- Tournament simulation now emits interest decision events for observability:
  - `tournament_interest_evaluated`
  - `tournament_interest_committed`
- There is no separate local-zone runtime surface in this architecture.

## API (read-only)

Example endpoints:

- `GET /api/worlds/{id}`
- `GET /api/characters/{id}`
- `GET /api/worlds/{id}/tiles?x=0&y=0`

## Tests

```bash
php bin/phpunit
```

Tests use SQLite via `.env.test` and create/reset schema automatically.
