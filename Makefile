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

shell:
	docker compose exec php bash

logs:
	docker compose logs -f

tinker:
	docker compose exec php php artisan tinker

worker:
	docker compose exec php php artisan queue:work redis --sleep=3 --tries=3

queue-restart:
	docker compose exec php php artisan queue:restart

queue-failed:
	docker compose exec php php artisan queue:failed

queue-retry:
	docker compose exec php php artisan queue:retry all
