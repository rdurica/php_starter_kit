# ENV
DOCKER_COMP = docker compose
PHP      = $(PHP_CONT) php
PHP_CONT = $(DOCKER_COMP) exec php-fpm

## Initialize containers
init:
	if [ ! -f build/dev/certs/server.key ]; then openssl req -x509 -newkey rsa:2048 -keyout build/dev/certs/server.key -out build/dev/certs/server.crt -days 3650 -nodes \
		  -subj "/C=CZ/ST=Praha/L=Praha/O=LocalOrg/CN=localhost"; fi
		  @$(DOCKER_COMP) build --pull --no-cache;
		  @$(DOCKER_COMP) up --detach; \
  		  mv build/dev/.github .github;

## Docker
rebuild: ## Builds the Docker images
	@$(DOCKER_COMP) build

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh:
	@$(PHP_CONT) bash

## Utils
cert:
	openssl req -x509 -newkey rsa:2048 -keyout build/dev/certs/server.key -out build/dev/certs/server.crt -days 3650 -nodes \
    		  -subj "/C=CZ/ST=Praha/L=Praha/O=LocalOrg/CN=localhost"

## Manifest for k8s
TEMPLATE = build/prod/manifest-template.yaml
OUTPUT_DIR = .
.PHONY: manifest clean

manifest:
	@echo "Generating manifest for $(app_name) ..."
	cp $(TEMPLATE) $(OUTPUT_DIR)/manifest.yaml && \
	sed -i "s/{{APP_NAME}}/$(app_name)/g" $(OUTPUT_DIR)/manifest.yaml && \
	sed -i "s|{{APP_SECRET}}|$(shell openssl rand -base64 32)|g" $(OUTPUT_DIR)/manifest.yaml