.PHONY: help setup install dev stop build test deploy-all deploy-api deploy-webapps health clean autopilot-migrate docs rbac-check ai-coverage modules test-mobile lint type-check

# Colors
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

help:
	@echo "$(GREEN)════════════════════════════════════════$(NC)"
	@echo "$(GREEN)  Widehalo ERP - Available Commands$(NC)"
	@echo "$(GREEN)════════════════════════════════════════$(NC)"
	@echo ""
	@echo "$(YELLOW)Setup & Installation:$(NC)"
	@echo "  make setup              Setup entire project"
	@echo "  make install            Install dependencies only"
	@echo ""
	@echo "$(YELLOW)Development:$(NC)"
	@echo "  make dev                Start all services in dev mode"
	@echo "  make dev-api            Start only API server"
	@echo "  make dev-webapp-biz     Start only Business webapp"
	@echo "  make dev-webapp-shop    Start only Ecommerce webapp"
	@echo "  make dev-mobile         Start mobile development"
	@echo "  make stop               Stop all services"
	@echo ""
	@echo "$(YELLOW)Build & Testing:$(NC)"
	@echo "  make build              Build all applications"
	@echo "  make build-api          Build API only"
	@echo "  make build-webapps      Build webapps only"
	@echo "  make test               Run all tests"
	@echo "  make test-api           Run API tests"
	@echo "  make test-e2e           Run E2E tests"
	@echo ""
	@echo "$(YELLOW)Deployment:$(NC)"
	@echo "  make deploy-all env=production       Deploy all services"
	@echo "  make deploy-api env=production       Deploy API only"
	@echo "  make deploy-webapp-biz env=production  Deploy Business webapp"
	@echo "  make deploy-webapp-shop env=production Deploy Ecommerce webapp"
	@echo ""
	@echo "$(YELLOW)Infrastructure:$(NC)"
	@echo "  make docker-up          Start Docker services"
	@echo "  make docker-down        Stop Docker services"
	@echo "  make docker-restart     Restart Docker services"
	@echo "  make health             Check all service health"
	@echo "  make logs               Show Docker logs"
	@echo ""
	@echo "$(YELLOW)Database:$(NC)"
	@echo "  make migrate            Run migrations"
	@echo "  make seed               Seed database"
	@echo "  make reset-db           Reset database (⚠️ destructive)"
	@echo ""
	@echo "$(YELLOW)Cleanup:$(NC)"
	@echo "  make clean              Remove build artifacts & cache"
	@echo "  make clean-all          Full cleanup (⚠️ removes data)"
	@echo ""
	@echo "$(YELLOW)Quality & Diagnostics:$(NC)"
	@echo "  make lint               Run ESLint on all frontend apps"
	@echo "  make type-check         TypeScript check on all frontend apps"
	@echo "  make test-mobile        Run Jest tests on React Native app"
	@echo "  make rbac-check         Check RBAC guards present on all API routes"
	@echo "  make ai-coverage        Find Vue pages missing useAiAssistant"
	@echo "  make modules            List all enabled Laravel modules"
	@echo "  make autopilot-migrate  Run autopilot_configs migration"
	@echo "  make docs               Verify docs folder structure"
	@echo ""

# ============================================================================
# SETUP & INSTALLATION
# ============================================================================

setup:
	@echo "$(YELLOW)Running complete project setup...$(NC)"
	@bash scripts/setup-all.sh

install:
	@echo "$(YELLOW)Installing dependencies...$(NC)"
	@cd apps/api && composer install && npm install
	@cd ../webapp-business && npm ci
	@cd ../webapp-ecommerce && npm ci
	@cd ../mobile && npm ci

# ============================================================================
# DEVELOPMENT
# ============================================================================

dev:
	@echo "$(YELLOW)Starting all development services...$(NC)"
	@bash scripts/start-dev.sh

dev-api:
	@echo "$(YELLOW)Starting API development server (Laravel on :8000)...$(NC)"
	@cd apps/api && php artisan serve --host=0.0.0.0 --port=8000

dev-webapp-biz:
	@echo "$(YELLOW)Starting Business webapp development...$(NC)"
	@cd apps/webapp-business && npm run dev

dev-webapp-shop:
	@echo "$(YELLOW)Starting Ecommerce webapp development...$(NC)"
	@cd apps/webapp-ecommerce && npm run dev

dev-mobile:
	@echo "$(YELLOW)Starting mobile development...$(NC)"
	@cd apps/mobile && npx expo start

stop:
	@echo "$(YELLOW)Stopping all services...$(NC)"
	@pkill -f "npm run dev" || true
	@pkill -f "expo start" || true
	@echo "$(GREEN)Services stopped$(NC)"

# ============================================================================
# BUILD & TESTING
# ============================================================================

build: build-api build-webapps
	@echo "$(GREEN)All builds complete$(NC)"

build-api:
	@echo "$(YELLOW)Building API...$(NC)"
	@cd apps/api && composer install --no-dev && php artisan optimize

build-webapps: build-webapp-biz build-webapp-shop
	@echo "$(GREEN)All webapps built$(NC)"

build-webapp-biz:
	@echo "$(YELLOW)Building Business webapp...$(NC)"
	@cd apps/webapp-business && npm ci && npm run build

build-webapp-shop:
	@echo "$(YELLOW)Building Ecommerce webapp...$(NC)"
	@cd apps/webapp-ecommerce && npm ci && npm run build

test: test-api test-e2e
	@echo "$(GREEN)All tests passed$(NC)"

test-api:
	@echo "$(YELLOW)Running API tests...$(NC)"
	@cd apps/api && vendor/bin/pest --no-coverage

test-e2e:
	@echo "$(YELLOW)Running E2E tests...$(NC)"
	@cd testing/e2e && npm test

# ============================================================================
# DEPLOYMENT
# ============================================================================

ENV ?= production
PARALLEL ?= false

deploy-all:
	@echo "$(YELLOW)Deploying all services to $(ENV)...$(NC)"
	@bash infrastructure/scripts/deploy-all.sh $(ENV) $(PARALLEL)

deploy-api:
	@echo "$(YELLOW)Deploying API to $(ENV)...$(NC)"
	@bash infrastructure/scripts/deploy-api.sh $(ENV)

deploy-webapp-biz:
	@echo "$(YELLOW)Deploying Business webapp to $(ENV)...$(NC)"
	@bash infrastructure/scripts/deploy-webapp.sh business $(ENV)

deploy-webapp-shop:
	@echo "$(YELLOW)Deploying Ecommerce webapp to $(ENV)...$(NC)"
	@bash infrastructure/scripts/deploy-webapp.sh ecommerce $(ENV)

# ============================================================================
# INFRASTRUCTURE
# ============================================================================

docker-up:
	@echo "$(YELLOW)Starting Docker services...$(NC)"
	@docker-compose up -d
	@echo "$(GREEN)Docker services started$(NC)"

docker-down:
	@echo "$(YELLOW)Stopping Docker services...$(NC)"
	@docker-compose down
	@echo "$(GREEN)Docker services stopped$(NC)"

docker-restart: docker-down docker-up
	@echo "$(GREEN)Docker services restarted$(NC)"

docker-build:
	@echo "$(YELLOW)Building Docker images...$(NC)"
	@docker-compose build

health:
	@bash infrastructure/scripts/health-check.sh all

logs:
	@docker-compose logs -f

# ============================================================================
# DATABASE
# ============================================================================

migrate:
	@echo "$(YELLOW)Running database migrations...$(NC)"
	@cd apps/api && php artisan migrate

seed:
	@echo "$(YELLOW)Seeding database...$(NC)"
	@cd apps/api && php artisan db:seed

reset-db:
	@echo "$(RED)⚠️  This will reset the database. Continue? [y/N]$(NC)"
	@read -r response; \
	if [ "$$response" = "y" ]; then \
		cd apps/api && php artisan migrate:fresh --seed; \
		echo "$(GREEN)Database reset complete$(NC)"; \
	else \
		echo "$(YELLOW)Cancelled$(NC)"; \
	fi

# ============================================================================
# CLEANUP
# ============================================================================

clean:
	@echo "$(YELLOW)Cleaning build artifacts...$(NC)"
	@rm -rf apps/api/bootstrap/cache/*
	@rm -rf apps/webapp-business/dist
	@rm -rf apps/webapp-ecommerce/dist
	@rm -rf apps/mobile/.expo
	@find . -name "*.log" -delete
	@echo "$(GREEN)Cleanup complete$(NC)"

clean-all: clean
	@echo "$(RED)⚠️  This will remove all data. Continue? [y/N]$(NC)"
	@read -r response; \
	if [ "$$response" = "y" ]; then \
		docker-compose down -v; \
		rm -rf storage/logs/*; \
		rm -rf vendor node_modules apps/*/node_modules; \
		echo "$(GREEN)Full cleanup complete$(NC)"; \
	else \
		echo "$(YELLOW)Cancelled$(NC)"; \
	fi

# ============================================================================
# QUALITY & DIAGNOSTICS
# ============================================================================

lint:
	@echo "$(YELLOW)Running ESLint on all frontend apps...$(NC)"
	@cd apps/webapp-business && npm run lint
	@cd apps/webapp-ecommerce && npm run lint
	@echo "$(GREEN)Lint complete$(NC)"

type-check:
	@echo "$(YELLOW)Running TypeScript check on all frontend apps...$(NC)"
	@cd apps/webapp-business && npm run type-check
	@cd apps/webapp-ecommerce && npm run type-check
	@echo "$(GREEN)Type check complete$(NC)"

test-mobile:
	@echo "$(YELLOW)Running React Native Jest tests...$(NC)"
	@cd apps/mobile && npm run test:ci
	@echo "$(GREEN)Mobile tests complete$(NC)"

autopilot-migrate:
	@echo "$(YELLOW)Running autopilot_configs migration...$(NC)"
	@cd apps/api && php artisan migrate --path=Modules/AI/database/migrations --force 2>/dev/null || \
	php artisan migrate --path=database/migrations/autopilot_configs.php --force 2>/dev/null || \
	php artisan migrate --force
	@echo "$(GREEN)Autopilot migration complete$(NC)"

rbac-check:
	@echo "$(YELLOW)Checking RBAC guards on API routes...$(NC)"
	@cd apps/api && php artisan route:list --columns=middleware,uri 2>/dev/null | \
		awk '/api\// && !/auth:sanctum/ && !/Sanctum/ {print "MISSING AUTH: " $$0}' | head -40 || true
	@echo ""
	@echo "$(YELLOW)Checking useRbac in Vue pages (webapp-business)...$(NC)"
	@TOTAL=$$(find apps/webapp-business/src -name "*.vue" | wc -l); \
	MISSING=$$(find apps/webapp-business/src -name "*.vue" -exec grep -rL "useRbac" {} \; | wc -l); \
	echo "  Pages without useRbac: $$MISSING / $$TOTAL"; \
	find apps/webapp-business/src -name "*.vue" -exec grep -rL "useRbac" {} \; | head -20
	@echo "$(GREEN)RBAC check complete$(NC)"

ai-coverage:
	@echo "$(YELLOW)Checking Vue pages missing useAiAssistant (webapp-business)...$(NC)"
	@TOTAL=$$(find apps/webapp-business/src/js/Pages -name "*.vue" 2>/dev/null | wc -l); \
	WITH_AI=$$(grep -rl "useAiAssistant" apps/webapp-business/src/js/Pages 2>/dev/null | wc -l); \
	WITHOUT_AI=$$(find apps/webapp-business/src/js/Pages -name "*.vue" -exec grep -rL "useAiAssistant" {} \; 2>/dev/null | wc -l); \
	echo "  webapp-business — with AI: $$WITH_AI / $$TOTAL, missing: $$WITHOUT_AI"; \
	find apps/webapp-business/src/js/Pages -name "*.vue" -exec grep -rL "useAiAssistant" {} \; 2>/dev/null | head -20
	@echo ""
	@echo "$(YELLOW)Checking Vue pages missing useAiAssistant (webapp-ecommerce)...$(NC)"
	@TOTAL=$$(find apps/webapp-ecommerce/src/js/Pages -name "*.vue" 2>/dev/null | wc -l); \
	WITHOUT_AI=$$(find apps/webapp-ecommerce/src/js/Pages -name "*.vue" -exec grep -rL "useAiAssistant" {} \; 2>/dev/null | wc -l); \
	WITH_AI=$$(grep -rl "useAiAssistant" apps/webapp-ecommerce/src/js/Pages 2>/dev/null | wc -l); \
	echo "  webapp-ecommerce — with AI: $$WITH_AI / $$TOTAL, missing: $$WITHOUT_AI"; \
	find apps/webapp-ecommerce/src/js/Pages -name "*.vue" -exec grep -rL "useAiAssistant" {} \; 2>/dev/null | head -20
	@echo ""
	@echo "$(YELLOW)Checking React Native screens missing useAiAssistant...$(NC)"
	@TOTAL=$$(find apps/mobile/app -name "*.tsx" 2>/dev/null | wc -l); \
	WITHOUT_AI=$$(find apps/mobile/app -name "*.tsx" -exec grep -rL "useAiAssistant\|AiAssistantCard" {} \; 2>/dev/null | wc -l); \
	WITH_AI=$$(grep -rl "useAiAssistant\|AiAssistantCard" apps/mobile/app 2>/dev/null | wc -l); \
	echo "  mobile — with AI: $$WITH_AI / $$TOTAL, missing: $$WITHOUT_AI"
	@echo "$(GREEN)AI coverage check complete$(NC)"

modules:
	@echo "$(YELLOW)Listing enabled Laravel modules...$(NC)"
	@cd apps/api && php artisan module:list 2>/dev/null || \
		(ls Modules/ | while read m; do echo "  $$m"; done)
	@echo "$(GREEN)Module list complete$(NC)"

docs:
	@echo "$(YELLOW)Verifying docs folder structure...$(NC)"
	@for dir in docs/01-GETTING-STARTED docs/02-ARCHITECTURE docs/04-API docs/06-DATABASE docs/07-DEPLOYMENT docs/09-RBAC docs/GUIDES; do \
		if [ -d "$$dir" ]; then \
			echo "  $(GREEN)OK$(NC)  $$dir"; \
		else \
			echo "  $(RED)MISSING$(NC)  $$dir"; \
		fi; \
	done
	@echo ""
	@echo "$(YELLOW)Markdown files outside /docs (potential misplacements):$(NC)"
	@find . -maxdepth 2 -name "*.md" \
		! -path "./docs/*" \
		! -path "./.git/*" \
		! -path "./node_modules/*" \
		! -name "README.md" \
		! -name "CLAUDE.md" \
		! -name "LICENSE.md" \
		! -name "CHANGELOG.md" \
		2>/dev/null | head -20 || true
	@echo "$(GREEN)Docs check complete$(NC)"

# Default target
.DEFAULT_GOAL := help
