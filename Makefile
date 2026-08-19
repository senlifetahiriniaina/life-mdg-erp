.PHONY: help install dev build test migrate seed deploy up down restart logs sh

# Colors
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

help:
	@echo "$(GREEN)════════════════════════════════════════$(NC)"
	@echo "$(GREEN)  Life MDG ERP - Available Commands$(NC)"
	@echo "$(GREEN)════════════════════════════════════════$(NC)"
	@echo ""
	@echo "$(YELLOW)Local development (no Docker):$(NC)"
	@echo "  make install    composer install && npm install"
	@echo "  make dev        php artisan serve + npm run dev (concurrently, via 'composer run dev')"
	@echo "  make build      Frontend production build (npm run build)"
	@echo "  make test       Backend test suite (vendor/bin/pest)"
	@echo "  make migrate    php artisan migrate"
	@echo "  make seed       php artisan db:seed"
	@echo ""
	@echo "$(YELLOW)Production deployment (Docker Compose + Caddy):$(NC)"
	@echo "  make deploy     Run scripts/deploy.sh — clone-to-HTTPS in one command"
	@echo "                  (see docs/07-DEPLOIEMENT/GUIDE-DEPLOIEMENT-SIMPLE.md)"
	@echo "  make up         docker compose -f docker-compose.prod.yml up -d"
	@echo "  make down       docker compose -f docker-compose.prod.yml down"
	@echo "  make restart    make down && make up"
	@echo "  make logs       docker compose -f docker-compose.prod.yml logs -f"
	@echo "  make sh         Shell into the running 'app' container"
	@echo ""

# ============================================================================
# LOCAL DEVELOPMENT
# ============================================================================

install:
	@echo "$(YELLOW)Installing dependencies...$(NC)"
	composer install
	npm install

dev:
	@echo "$(YELLOW)Starting dev servers (php artisan serve + queue + vite)...$(NC)"
	composer run dev

build:
	@echo "$(YELLOW)Building frontend assets...$(NC)"
	npm run build

test:
	@echo "$(YELLOW)Running backend test suite...$(NC)"
	vendor/bin/pest

migrate:
	@echo "$(YELLOW)Running database migrations...$(NC)"
	php artisan migrate

seed:
	@echo "$(YELLOW)Seeding database...$(NC)"
	php artisan db:seed

# ============================================================================
# PRODUCTION DEPLOYMENT (Docker Compose + Caddy — see scripts/deploy.sh)
# ============================================================================

COMPOSE_FILE := docker-compose.prod.yml

deploy:
	@bash scripts/deploy.sh

up:
	docker compose -f $(COMPOSE_FILE) up -d

down:
	docker compose -f $(COMPOSE_FILE) down

restart: down up

logs:
	docker compose -f $(COMPOSE_FILE) logs -f

sh:
	docker compose -f $(COMPOSE_FILE) exec app sh

# Default target
.DEFAULT_GOAL := help
