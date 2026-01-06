.PHONY: help up down restart build logs shell migrate fresh seed test lint analyze install update

# Colors
GREEN  := $(shell tput setaf 2)
YELLOW := $(shell tput setaf 3)
RESET  := $(shell tput sgr0)

# Default target
help:
	@echo ""
	@echo "${GREEN}Uživo Platform - Development Commands${RESET}"
	@echo ""
	@echo "${YELLOW}Docker Commands:${RESET}"
	@echo "  make up          - Start all containers"
	@echo "  make down        - Stop all containers"
	@echo "  make restart     - Restart all containers"
	@echo "  make build       - Rebuild containers"
	@echo "  make logs        - View container logs"
	@echo "  make logs-app    - View app container logs"
	@echo ""
	@echo "${YELLOW}Laravel Commands:${RESET}"
	@echo "  make shell       - Open bash in app container"
	@echo "  make artisan     - Run artisan command (use cmd=)"
	@echo "  make migrate     - Run migrations"
	@echo "  make fresh       - Fresh migration with seeds"
	@echo "  make seed        - Run database seeders"
	@echo "  make tinker      - Open Laravel Tinker"
	@echo ""
	@echo "${YELLOW}Quality Commands:${RESET}"
	@echo "  make test        - Run Pest tests"
	@echo "  make lint        - Run PHP CS Fixer"
	@echo "  make analyze     - Run PHPStan analysis"
	@echo "  make quality     - Run all quality checks"
	@echo ""
	@echo "${YELLOW}Setup Commands:${RESET}"
	@echo "  make install     - Initial project setup"
	@echo "  make update      - Update dependencies"
	@echo ""

# =============================================================================
# Docker Commands
# =============================================================================

up:
	docker compose up -d
	@echo "${GREEN}✓ Containers started${RESET}"
	@echo "  App:         http://localhost:8000"
	@echo "  Reverb:      ws://localhost:8080"
	@echo "  Meilisearch: http://localhost:7700"

down:
	docker compose down
	@echo "${GREEN}✓ Containers stopped${RESET}"

restart:
	docker compose restart
	@echo "${GREEN}✓ Containers restarted${RESET}"

build:
	docker compose build --no-cache
	@echo "${GREEN}✓ Containers rebuilt${RESET}"

logs:
	docker compose logs -f

logs-app:
	docker compose logs -f app

logs-queue:
	docker compose logs -f queue

logs-reverb:
	docker compose logs -f reverb

# =============================================================================
# Laravel Commands
# =============================================================================

shell:
	docker compose exec app sh

artisan:
	docker compose exec app php artisan $(cmd)

migrate:
	docker compose exec app php artisan migrate
	@echo "${GREEN}✓ Migrations completed${RESET}"

fresh:
	docker compose exec app php artisan migrate:fresh --seed
	@echo "${GREEN}✓ Fresh migration with seeds completed${RESET}"

seed:
	docker compose exec app php artisan db:seed
	@echo "${GREEN}✓ Seeding completed${RESET}"

tinker:
	docker compose exec app php artisan tinker

cache-clear:
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	@echo "${GREEN}✓ All caches cleared${RESET}"

cache:
	docker compose exec app php artisan config:cache
	docker compose exec app php artisan route:cache
	docker compose exec app php artisan view:cache
	@echo "${GREEN}✓ Caches generated${RESET}"

# =============================================================================
# Quality Commands
# =============================================================================

test:
	docker compose exec app php artisan test
	@echo "${GREEN}✓ Tests completed${RESET}"

test-coverage:
	docker compose exec app php artisan test --coverage
	@echo "${GREEN}✓ Tests with coverage completed${RESET}"

lint:
	docker compose exec app ./vendor/bin/pint
	@echo "${GREEN}✓ Code formatted${RESET}"

lint-check:
	docker compose exec app ./vendor/bin/pint --test
	@echo "${GREEN}✓ Lint check completed${RESET}"

analyze:
	docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M
	@echo "${GREEN}✓ Static analysis completed${RESET}"

quality: lint-check analyze test
	@echo "${GREEN}✓ All quality checks passed${RESET}"

# =============================================================================
# Setup Commands
# =============================================================================

install:
	@echo "${YELLOW}Installing Uživo Platform...${RESET}"
	cp -n .env.example .env || true
	docker compose build
	docker compose up -d
	docker compose exec app composer install
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate --seed
	docker compose exec app php artisan storage:link
	@echo ""
	@echo "${GREEN}✓ Installation complete!${RESET}"
	@echo ""
	@echo "  App:         http://localhost:8000"
	@echo "  Reverb:      ws://localhost:8080"
	@echo "  Meilisearch: http://localhost:7700"
	@echo "  PostgreSQL:  localhost:5432"
	@echo "  Redis:       localhost:6379"
	@echo ""

update:
	docker compose exec app composer update
	docker compose exec app php artisan migrate
	@echo "${GREEN}✓ Update completed${RESET}"

# =============================================================================
# Database Commands
# =============================================================================

db-reset:
	docker compose exec app php artisan migrate:fresh --seed
	@echo "${GREEN}✓ Database reset${RESET}"

db-backup:
	docker compose exec pgsql pg_dump -U uzivo uzivo > ./storage/backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "${GREEN}✓ Database backed up${RESET}"

# =============================================================================
# Reverb & Queue Commands
# =============================================================================

reverb:
	docker compose exec app php artisan reverb:start --debug

queue-restart:
	docker compose restart queue
	@echo "${GREEN}✓ Queue worker restarted${RESET}"

# =============================================================================
# Utility Commands
# =============================================================================

ps:
	docker compose ps

clean:
	docker compose down -v --remove-orphans
	@echo "${GREEN}✓ Containers and volumes removed${RESET}"
