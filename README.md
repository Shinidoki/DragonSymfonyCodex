# DragonSymfony

Symfony 8 backend foundation for a persistent, simulated Dragon Ball–inspired RPG world (worlds, characters, time
advancement, and stat growth).

## Requirements

- PHP 8.4+
- Composer
- A database configured via `DATABASE_URL` (default `.env` uses MariaDB/MySQL)

## Local setup

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
```

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

## Local mode (tick simulation)

Local mode is the “Active Zone”: actions are resolved in **ticks** (one action = one tick).

Enter local mode for a character (creates or reuses an active local session):

```bash
php bin/console game:local:enter --character=1 --width=8 --height=8
```

Apply tick actions:

```bash
php bin/console game:local:action --session=1 --type=move --dir=north
php bin/console game:local:action --session=1 --type=wait
```

Sleep/train are **long actions**: they temporarily suspend the local session, advance world time by days, then resume
the same local session at the same local position.

```bash
php bin/console game:local:sleep --session=1 --days=1
php bin/console game:local:train --session=1 --days=7 --context=auto
```

`--context=auto` currently resolves to `dojo` if the current world-map tile has a dojo flag, otherwise `wilderness`.
You can also force it: `--context=dojo` or `--context=mentor`.

Exit local mode (suspends the session):

```bash
php bin/console game:local:exit --session=1
```

## API (read-only)

Run a local server:

```bash
php -S localhost:8000 -t public
```

Example endpoints:

- `GET /api/worlds/{id}`
- `GET /api/characters/{id}`
- `GET /api/worlds/{id}/tiles?x=0&y=0`

## Tests

```bash
php bin/phpunit
```

Tests use SQLite via `.env.test` and create/reset schema automatically.
