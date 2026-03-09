up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

migrate:
	docker compose exec php php artisan migrate --seed

fresh:
	docker compose exec php php artisan migrate:fresh --seed

test:
	docker compose exec php php artisan test

shell-php:
	docker compose exec php bash

shell-ui:
	docker compose exec ui sh

logs:
	docker compose logs -f

tinker:
	docker compose exec php php artisan tinker

npm-install:
	docker compose exec ui npm install
