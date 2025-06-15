# PHP Starter Kit

[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](http://php.net)
[![Docker](https://img.shields.io/badge/Docker-powered-blue.svg)](https://www.docker.com/)
[![composer](https://img.shields.io/badge/composer-latest-green.svg)](https://getcomposer.org/)

![php8](https://github.com/user-attachments/assets/265bf808-0e8e-40a8-87fe-f473a708208d)

"PHP Starter Kit" is a blank  preconfigured docker template for building PHP applications.

## Overview

The starter kit is built on the latest stable version of PHP and includes all the necessary extensions and libraries to
get you started. This repository is intended to be a starting point for new projects and can be easily customized to fit
the specific needs of your project. With this starter kit, developers can focus on writing code and not worry
about the underlying setup. 

Key aspects of the starter kit:
 - The Docker image is based on latest php-fpm-alpine with NGINX (using UNIX sockets).
 - Minimal image size while still providing all the necessary tools and extensions (~65 MB).
 - Dedicated network `apps` for local development.
 - SSL (HTTP/2) for localhost
 - Makefile for easy management of the Docker container.
 - Nodejs + npm for dev environment.

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
- `make rebuild:` Rebuild the Docker image
- `make up:` Start the containers in detached mode (docker-compose up -d)
- `make down:` DStop and remove containers
- `make logs:` Show logs from all containers
- `make php:` Open a shell inside the PHP container
- `make node:` Open a shell inside the Node.js container
- `make manifest app_name=<$name>:` Generate example manifest for k8s. (for example make manifest app_name=app1).

By default nginx pointing to `/src/public` folder.

## Contributing

If you would like to contribute to this project, please fork the repository and create a pull request. We welcome all
contributions, including bug fixes, new features, and documentation improvements.

## License

This project is licensed under the terms of the MIT license.