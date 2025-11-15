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
