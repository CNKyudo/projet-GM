up:
	docker-compose build
	docker-compose up -d
	docker-compose exec php-fpm composer install
	docker-compose exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction
	docker-compose exec php-fpm php bin/console tailwind:build

tailwind:
	docker-compose exec php-fpm php bin/console tailwind:build

tailwind-watch:
	docker-compose exec php-fpm php bin/console tailwind:build --watch

down:
	docker-compose down

php:
	docker-compose exec -u 1000 php-fpm bash

migrate:
	docker-compose exec php-fpm php bin/console doctrine:migrations:diff
	docker-compose exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction
