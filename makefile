# ENV
DOCKER_COMP = docker compose
PHP      = $(PHP_CONT) php
PHP_CONT = $(DOCKER_COMP) exec php-fpm
NODE_CONT = $(DOCKER_COMP) exec node

## Initialize containers
init:
	if [ ! -d build/dev/certs ]; then mkdir -p build/dev/certs; fi
	if [ ! -f build/dev/certs/tls.crt ]; then mkcert -key-file build/dev/certs/tls.key -cert-file build/dev/certs/tls.crt localhost; fi
		  rm -f src/.gitkeep
		  docker network inspect apps >/dev/null 2>&1 || docker network create apps;
		  @$(DOCKER_COMP) build --pull --no-cache;
		  @$(DOCKER_COMP) up --detach; \
  		  mv build/dev/.github .github;

## Docker
rebuild: ## Builds the Docker images
	@$(DOCKER_COMP) build php-fpm
	@$(DOCKER_COMP) build --pull --no-cache
	@$(DOCKER_COMP) up --detach

reload: ## Builds the Docker images
	@$(DOCKER_COMP) build php-fpm
	@$(DOCKER_COMP) build
	@$(DOCKER_COMP) up --detach

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

php:
	@$(PHP_CONT) bash

node:
	@$(NODE_CONT) bash

node-sync:
	sudo docker compose cp node:/app/src/node_modules ./src