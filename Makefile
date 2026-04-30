DOCKER_VERSION := $(shell docker version --format '{{.Server.Version}}')
DOCKER_COMPOSE_CMD := $(shell [ "$(DOCKER_VERSION)" \< "20.10.6" ] && echo docker-compose || echo docker compose)

up:
# 	$(DOCKER_COMPOSE_CMD) build
	$(DOCKER_COMPOSE_CMD) up -d
	$(DOCKER_COMPOSE_CMD) exec php-fpm composer install
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console tailwind:build

tailwind:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console tailwind:build

tailwind-watch:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console tailwind:build --watch

down:
	$(DOCKER_COMPOSE_CMD) down

php:
	$(DOCKER_COMPOSE_CMD) exec -u 1000 php-fpm bash

diff:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:diff

migrate:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction

reset-database:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:database:drop --force
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:database:create
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:fixtures:load --no-interaction

test-functional:
	@echo "Preparing test database..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:database:create --env=test --if-not-exists
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:migrate --env=test --no-interaction
	@echo "Loading fixtures..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:query:sql "SET session_replication_role = replica" --env=test
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:fixtures:load --env=test --no-interaction --purge-with-truncate
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:query:sql "SET session_replication_role = DEFAULT" --env=test
	@echo "Running functional tests..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/phpunit tests/Functional/ --testdox

fix: rector csfixer phpstan

rector:
	@echo "Running rector..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm vendor/bin/rector process --config=tools/rector.php

csfixer:
	@echo "Running php-cs-fixer..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm vendor/bin/php-cs-fixer fix --config=tools/.php-cs-fixer.php --allow-risky=yes

phpstan:
	@echo "Running phpstan analyse (configuration tools/phpstan.neon)..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm vendor/bin/phpstan analyse --configuration=tools/phpstan.neon

.PHONY: fix rector phpstan csfixer test-functional
