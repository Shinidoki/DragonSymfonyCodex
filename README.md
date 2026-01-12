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

Advance simulation (MVP: every character trains once per day):

```bash
php bin/console game:sim:advance --world=1 --days=7
```

## API (read-only)

Run a local server:

```bash
php -S localhost:8000 -t public
```

Example endpoints:

- `GET /api/worlds/{id}`
- `GET /api/characters/{id}`

## Tests

```bash
php bin/phpunit
```

Tests use SQLite via `.env.test` and create/reset schema automatically.

