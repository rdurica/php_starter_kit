# Agent Context for PHP Starter Kit

## Stack

- **Backend**: PHP 8.5+, FrankenPHP (Caddy-powered PHP server)
- **Frontend**: Vite, Node.js 20+
- **Supported Frameworks**: Laravel, Symfony, Nette
- **Database**: PostgreSQL (recommended), MySQL/MariaDB, SQLite
- **Cache/Sessions/Queue**: Redis
- **Infrastructure**: Docker (FrankenPHP)

## Dev Environment

All development runs inside Docker containers via `docker compose`.

| Command               | Description                                          |
| --------------------- | ---------------------------------------------------- |
| `make init`           | First-time setup: network, images, containers        |
| `make up`             | Start containers detached                            |
| `make down`           | Stop containers                                      |
| `make logs`           | Stream logs                                          |
| `make php`            | Shell into FrankenPHP container                      |

**All `php artisan`, `bin/console`, and `npm` commands must run inside containers:**

```shell
docker compose exec frankenphp php artisan <cmd>
docker compose exec frankenphp bin/console <cmd>
docker compose exec frankenphp npm <cmd>
```

## Key Commands

```shell
# Setup (inside container)
composer install

# Laravel
php artisan key:generate
php artisan migrate
php artisan test

# Symfony
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
vendor/bin/phpunit

# Frontend (inside container)
npm install
npm run dev          # Vite dev server
npm run build        # production build
```
