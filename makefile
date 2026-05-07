# ENV
DOCKER_COMP = docker compose
PHP      = $(PHP_CONT) php
PHP_CONT = $(DOCKER_COMP) exec --user=robbyte frankenphp

## Initialize containers
init:
	rm -f src/.gitkeep
	docker network inspect apps >/dev/null 2>&1 || docker network create apps;
	@$(DOCKER_COMP) build --pull --no-cache;
	@$(DOCKER_COMP) up --detach;

## Docker
rebuild: ## Builds the Docker images (no cache)
	@$(DOCKER_COMP) build --pull --no-cache
	@$(DOCKER_COMP) up --detach

reload: ## Builds the Docker images
	@$(DOCKER_COMP) build
	@$(DOCKER_COMP) up --detach

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

php: ## Open shell in FrankenPHP container
	@$(PHP_CONT) bash

setup-githooks:
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-commit

trust-cert: ## Trust Caddy's local CA certificate (Fedora/Debian)
	@echo "Extracting Caddy root CA certificate..."
	@$(DOCKER_COMP) exec frankenphp cat /data/caddy/pki/authorities/local/root.crt > /tmp/caddy-root.crt 2>/dev/null || (echo "Error: Failed to extract certificate. Is FrankenPHP running?" && exit 1)
	@if command -v update-ca-certificates >/dev/null 2>&1; then \
		echo "Detected Debian/Ubuntu-based system..."; \
		sudo cp /tmp/caddy-root.crt /usr/local/share/ca-certificates/caddy-root.crt; \
		sudo update-ca-certificates; \
	elif command -v update-ca-trust >/dev/null 2>&1; then \
		echo "Detected Fedora/RHEL-based system..."; \
		sudo cp /tmp/caddy-root.crt /etc/pki/ca-trust/source/anchors/caddy-root.crt; \
		sudo update-ca-trust extract; \
	else \
		echo "Error: Could not detect certificate management tool."; \
		echo "Please manually install /tmp/caddy-root.crt into your system trust store."; \
		exit 1; \
	fi
	@echo "Certificate trusted successfully. Restart Chrome: chrome://restart"
	@rm -f /tmp/caddy-root.crt
