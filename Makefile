DOCKER_VERSION := $(shell docker version --format '{{.Server.Version}}')
DOCKER_COMPOSE_CMD := $(shell [ "$(DOCKER_VERSION)" \< "20.10.6" ] && echo docker-compose || echo docker compose)

up:
	$(DOCKER_COMPOSE_CMD) build
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

migrate:
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:diff
	$(DOCKER_COMPOSE_CMD) exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction

fix: csfixer phpstan

csfixer:
	@echo "Running php-cs-fixer..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm vendor/bin/php-cs-fixer fix --config=tools/.php-cs-fixer.php --allow-risky=yes

phpstan:
	@echo "Running phpstan analyse (configuration tools/phpstan.neon)..."
	$(DOCKER_COMPOSE_CMD) exec php-fpm vendor/bin/phpstan analyse --configuration=tools/phpstan.neon

.PHONY: fix phpstan csfixer
