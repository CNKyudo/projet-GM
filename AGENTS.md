# AGENTS.md

## Development cycle

- ALWAYS start by creating an integration test which tests the bug (if the user asks you to correct a bug), or the wanted functionality.
- If the user hasn't given you detailed enough information on the feature/bug, or if you see in the code something which contradicts what the user said/asked for, you MUST raise this concern and ask for precisions.
- The test MUST be a failing test at this point, since you haven't yet developed anything.
- ALWAYS run `make fix` and `make test-functional` after having finished modifying the code.
- The test MUST succeed at this point, to validate that your development correctly

## Environment

- **All commands run inside Docker containers.** Use `docker compose exec php-fpm <cmd>` or the Makefile commands.
- PostgreSQL 16 database. Container name: `database`, DB: `app`, user: `app`, password: `password`.
- Symfony 7.4 + PHP 8.4.

## Makefile Commands

- `make diff`: Generate a migration diff
- `make migrate`: Runs the migrations
- `make test-functional`: Prep test DB (create, migrate, load fixtures), then run `tests/Functional/` with `--testdox`
- `make rector` Run Rector with PHP 8.4 + dead code + coding style presets
- `make csfixer`: Run php-cs-fixer (short array syntax, @Symfony + @PSR12)
- `make phpstan`: Run PHPStan level 7 on `src/` and `tests/`
- `make fix`: Run rector → php-cs-fixer → phpstan (in that order)

The Make commands are made to be run outside docker, they invoke commands into docker themselves.
Tool config files live in `tools/` (e.g. `tools/phpstan.neon`, `tools/.php-cs-fixer.php`, `tools/rector.php`). The Makefile uses those paths. Composer scripts reference root-level paths that don't exist; always use `make` targets.

## Code style

- @Symfony + @PSR12, short array syntax.
- `declare(strict_types=1)` required (enforced by tools).
