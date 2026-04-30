# AGENTS.md

## Environment

- **All commands run inside Docker containers.** Use `docker compose exec php-fpm <cmd>` or the Makefile targets.
- PostgreSQL 16 database. Container name: `database`, DB: `app`, user: `app`, password: `password`.
- Symfony 7.4 + PHP 8.4.
- Test env creates a separate database. Migrations must be run for the test DB before tests.

## Commands (via Makefile)

| Make target            | What it does                                                                                 |
|------------------------|----------------------------------------------------------------------------------------------|
| `make up`              | Build & start containers, composer install, run migrations, recompile Tailwind               |
| `make down`            | Stop containers                                                                              |
| `make php`             | Open bash in the php-fpm container (as uid 1000)                                             |
| `make diff`            | Generate a migration diff                                                                    |
| `make migrate`         | Runs the migrations<br/>                                                                          |
| `make tailwind`        | Rebuild Tailwind CSS                                                                         |
| `make tailwind-watch`  | Rebuild Tailwind CSS in watch mode                                                           |
| `make test-functional` | Prep test DB (create, migrate, load fixtures), then run `tests/Functional/` with `--testdox` |
| `make fix`             | Run rector → php-cs-fixer → phpstan (in that order)                                          |
| `make csfixer`         | Run php-cs-fixer (short array syntax, @Symfony + @PSR12)                                     |
| `make phpstan`         | Run PHPStan level 7 on `src/` and `tests/`                                                   |
| `make rector`          | Run Rector with PHP 8.4 + dead code + coding style presets                                   |

Tool config files live in `tools/` (e.g. `tools/phpstan.neon`, `tools/.php-cs-fixer.php`, `tools/rector.php`). The Makefile uses those paths. Composer scripts reference root-level paths that don't exist; always use `make` targets.

## Running a single test

```bash
docker compose exec php-fpm php bin/phpunit tests/Functional/SomeTest.php --testdox
```

## Test fixtures and PostgreSQL FK constraint workaround

- `AbstractWebTestCase::setUp()` loads `AppFixtures` programmatically before every test.
- Because `TRUNCATE CASCADE` is blocked by FK constraints on PostgreSQL, the base class executes `SET session_replication_role = replica` before truncate and `SET session_replication_role = DEFAULT` after.
- `make test-functional` does the same via CLI when loading fixtures for the test DB.
- Foundry (zenstruck/foundry) is available in dev/test. Fixtures use Doctrine Fixtures + Foundry Story classes in `src/Story/`.

## Architecture notes

- **Routing**: attributes on controllers in `src/Controller/` (configured in `config/routes.yaml`).
- **Frontend**: Symfony Asset Mapper + importmap (`importmap.php`) + Tailwind CSS via `symfonycasts/tailwind-bundle`. JS entrypoints: `app.js`, `equipment_form.js`, `club_index.js`, `flash_toasts.js`. Hotwired Turbo + Stimulus.
- **Async messaging**: Messenger with doctrine transport (`doctrine://default?auto_setup=0`).
- **Mailer**: SMTP to `mailer:1025` (Mailpit in dev).
- **Pagination**: KnpPaginatorBundle.
- **Doctrine behaviors**: StofDoctrineExtensionsBundle (Gedmo), primarily for Timestampable/Sluggable.
- **Security**: Symfony security + reset password (symfonycasts/reset-password-bundle). `loginUser()` via Symfony WebTestCase is used in tests.

## Deploy

- On push to `main`, GitHub Actions copies `.github/workflows/deploy.sh` to the OVH VPS and executes it.
- The deploy script: clones the repo, copies `.env.local` from the current release, runs `composer install --no-dev`, cache:clear, importmap:install, tailwind:build --minify, asset-map:compile, migrations:migrate, then atomically swaps the symlink.

## Code style

- @Symfony + @PSR12, short array syntax.
- `declare(strict_types=1)` required (enforced by tools).
