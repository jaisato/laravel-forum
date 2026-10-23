# Laravel Forum: shortcuts for the Docker Compose environment (compose.yaml).
# `make help` lists them; docs/03-desarrollo-local.md tells the whole story.

.DEFAULT_GOAL := help
.PHONY: help setup up down ps logs shell tinker migrate fresh test test-pgsql \
	test-js coverage lint fix

# The dev image runs as the host user, so whatever it writes in the mounted
# code (vendor, node_modules, caches) belongs to you.
export HOST_UID ?= $(shell id -u)
export HOST_GID ?= $(shell id -g)

COMPOSE ?= docker compose
# A one-off container next to the running services (setup needs no web).
RUN = $(COMPOSE) run --rm --no-deps app
EXEC = $(COMPOSE) exec app

help: ## List the shortcuts
	@awk 'BEGIN { FS = ":.*## " } /^[a-z-]+:.*## / { printf "  \033[36m%-11s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# Safe to run again: the demo data only goes into a database without tables.
setup: ## First start: .env, image, dependencies, database with demo data
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d --wait postgres redis meilisearch mailpit
	$(RUN) composer install
	@grep -q '^APP_KEY=base64:' .env || $(RUN) php artisan key:generate
	$(RUN) php artisan storage:link --force
	@if $(RUN) php artisan migrate:status > /dev/null 2>&1; then \
		$(RUN) php artisan migrate; \
	else \
		$(RUN) php artisan migrate --seed; \
	fi
	$(RUN) php artisan scout:sync-index-settings
	$(RUN) npm ci
	$(COMPOSE) up -d --wait
	@echo "Forum: $$(grep -E '^APP_URL=' .env | cut -d= -f2-) - emails: http://localhost:8025"

up: ## Start the forum (http://localhost:8080)
	$(COMPOSE) up -d --wait

down: ## Stop it (the data stays in the volumes)
	$(COMPOSE) down

ps: ## The containers and their health
	$(COMPOSE) ps

logs: ## Follow the logs (one service: make logs s=queue)
	$(COMPOSE) logs -f --tail=100 $(s)

shell: ## A shell in the app container
	$(EXEC) sh

tinker: ## Laravel's REPL
	$(EXEC) php artisan tinker

migrate: ## Run the pending migrations
	$(EXEC) php artisan migrate

fresh: ## Rebuild the database from scratch, with demo data
	$(EXEC) php artisan migrate:fresh --seed
	$(EXEC) php artisan scout:sync-index-settings

test: ## PHP suite (SQLite in memory)
	$(EXEC) composer test

# DB_USERNAME and DB_PASSWORD come from .env, as in the postgres service.
PGSQL_TEST_ENV = DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 DB_DATABASE=forum_testing

test-pgsql: ## PHP suite on PostgreSQL 18 (database forum_testing)
	$(EXEC) sh -c 'export $$(grep -E "^DB_(USERNAME|PASSWORD)=" .env | xargs) $(PGSQL_TEST_ENV) && composer test'

test-js: ## Vitest suite
	$(EXEC) npm test

coverage: ## Both suites with coverage (PHP at least 95 %)
	$(EXEC) composer test:coverage
	$(EXEC) npm run test:coverage

lint: ## Pint, Larastan, ESLint, Prettier, vue-tsc and hadolint
	$(EXEC) composer lint:check
	$(EXEC) composer analyse
	$(EXEC) npm run lint
	$(EXEC) npm run format:check
	$(EXEC) npm run types:check
	docker run --rm -i -v "$(CURDIR)/.hadolint.yaml:/.config/hadolint.yaml:ro" \
		hadolint/hadolint:v2.14.0-alpine hadolint --config /.config/hadolint.yaml - < Dockerfile

fix: ## Fix the code style (Pint, ESLint and Prettier)
	$(EXEC) composer lint
	$(EXEC) npm run lint:fix
	$(EXEC) npm run format
