# PHP Starter Kit

[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](http://php.net)
[![Docker](https://img.shields.io/badge/Docker-powered-blue.svg)](https://www.docker.com/)
[![composer](https://img.shields.io/badge/composer-latest-green.svg)](https://getcomposer.org/)

![php8](https://github.com/user-attachments/assets/265bf808-0e8e-40a8-87fe-f473a708208d)

"PHP Starter Kit" is a blank preconfigured docker template for building PHP applications.

## Overview

This PHP Starter Kit provides a ready-to-use, out-of-the-box local development environment for modern PHP projects. It comes preconfigured with
everything you need to start coding immediately, without wasting time on setup and configuration.
The starter kit is built on the latest stable PHP version and includes all essential PHP extensions, a minimal and efficient Docker image, plus
integrated support for popular PHP frameworks.

**Key aspects of the starter kit:**
- Docker image based on the latest php-fpm-alpine with NGINX using UNIX sockets for high performance.
- Minimal production image size (~65 MB) while including all necessary tools and extensions.
- Dedicated Docker network for local development.
- Automatic SSL (HTTP/2) support with easy self-signed certificate generation.
- Makefile included for simple container lifecycle management (init, rebuild, up, down, logs, etc.).
- Node.js + npm included for frontend tooling and development (Vite, asset bundling).
- Designed for maximum flexibility and ease use.
- Enables hot reloading and smooth frontend-backend integration out of the box.

## Supported Frameworks

This starter kit is ready to use out of the box with popular PHP frameworks such as Laravel, Symfony, and Nette.

<p align="left">
  <img src="https://laravel.com/img/logomark.min.svg" alt="Laravel" width="40" height="40" style="margin-right:10px;">
  <img src="https://symfony.com/logos/symfony_black_03.png" alt="Symfony" width="40" height="40" style="margin-right:10px;">
  <img src="https://avatars.githubusercontent.com/u/99965?s=200&v=4" alt="Nette" width="40" height="40">
</p>

## HTTPS and SSL Certificates

For local HTTPS development, it is required to have mkcert installed. This tool helps you generate trusted self-signed certificates.

```shell
mkcert -install
```

## Getting Started

1. Build the Docker image & generate ssl certificates: `make init`
2. Access the application in your browser at https://localhost

After initial instalation you can use these commands:

- `make rebuild:` Rebuild the Docker image (--pull --no-cache)
- `make reload:` Rebuild the Docker image(with cache).
- `make up:` Start the containers in detached mode (docker-compose up -d)
- `make down:` DStop and remove containers
- `make logs:` Show logs from all containers
- `make php:` Open a shell inside the PHP container
- `make node:` Open a shell inside the Node.js container
- `make manifest app_name=<$name>:` Generate example manifest for k8s. (for example `make manifest app_name=app1`).

## Additional configuration & setup

### <img src="https://laravel.com/img/logomark.min.svg" alt="Laravel" width="25" height="25" style="margin-right:10px;">Laravel

in v**ite.config.js** add a server section.

```js
import fs from 'fs';

export default defineConfig({
    server: {
        https: {
            key: fs.readFileSync('/etc/nginx/certs/tls.key'),
            cert: fs.readFileSync('/etc/nginx/certs/tls.crt'),
        },
        host: '0.0.0.0',
        port: 5173,
        origin: 'https://localhost:5173',
        cors: {
            origin: 'https://localhost',
            credentials: true,
        }
    }
});
```

If you’re starting a new Laravel project, simply enter the php container and run the official Laravel interactive installer:
```shell
make php
laravel
```

### <img src="https://symfony.com/logos/symfony_black_03.png" alt="Symfony" width="25" height="25" style="margin-right:10px;">Symfony
If you’re starting a new Symfony project, simply enter the php container and run initialize new project in current directory:
```shell
make php
symfony new . --webapp --no-git
```

### <img src="https://avatars.githubusercontent.com/u/99965?s=200&v=4" alt="Laravel" width="25" height="25" style="margin-right:10px;">Nette
If you’re starting a new Nette project, simply enter the php container and run initialize new project:
```shell
make php
nette
```

You need to change the default document root from `public/` to `www/`.
Before building the containers, update the NGINX configuration in `build/dev/nginx/default.conf`:

```editorconfig
server {
# /app/src/public -> /app/src/www
root        /app/src/www ;

location ~ \.php$ {
    # /app/src/public -> /app/src/www
    fastcgi_param        SCRIPT_FILENAME /app/src/www$fastcgi_script_name ;
}
```

Note: If you have already initialized the containers, after making changes you can simply run:
```shell
make reload
```

## Contributing

If you would like to contribute to this project, please fork the repository and create a pull request. We welcome all
contributions, including bug fixes, new features, and documentation improvements.

## License

This project is licensed under the terms of the MIT license.