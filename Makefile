DC = docker compose exec app

.PHONY: test migrate tinker serve queue fresh seed artisan shell up down rebuild logs

test:
	$(DC) php artisan test $(filter-out $@,$(MAKECMDGOALS))

migrate:
	$(DC) php artisan migrate

fresh:
	$(DC) php artisan migrate:fresh

seed:
	$(DC) php artisan db:seed

tinker:
	$(DC) php artisan tinker

queue:
	$(DC) php artisan queue:work redis --sleep=3 --tries=3

artisan:
	$(DC) php artisan $(filter-out $@,$(MAKECMDGOALS))

shell:
	docker compose exec app sh

up:
	docker compose up -d

down:
	docker compose down

rebuild:
	docker compose up -d --build

logs:
	docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))

%:
	@: