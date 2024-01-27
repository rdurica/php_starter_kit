# PHP Starter Kit

[![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](http://php.net)
[![Docker](https://img.shields.io/badge/Docker-powered-blue.svg)](https://www.docker.com/)
[![composer](https://img.shields.io/badge/composer-latest-green.svg)](https://getcomposer.org/)

![php8](https://github.com/rdurica/php_starter_kit/assets/16089770/4430bff1-85af-474a-91ac-80f560c923d8)
"PHP Starter Kit" is a blank PHP application template that includes a Docker image pre-configured with PHP, Composer,
Opcache with JIT.

## Overview

The Docker image provides a consistent and reproducible environment, which is particularly useful when working with a
team of developers or deploying to production. With this starter kit, developers can focus on writing code and not worry
about the underlying setup.

The starter kit is built on the latest stable version of PHP and includes all the necessary extensions and libraries to
get you going. Additionally, it provides the ability to easily install and manage dependencies using Composer, and the
ability to debug your application using Xdebug.

This repository is intended to be a starting point for new projects and can be easily customized to fit the specific
needs of your project.

## Aliases
- **ll**: ls -lah
- **c**: composer

## Getting Started

1. Clone the repository: git clone https://github.com/rdurica/php_starter_kit.git
2. Build the Docker image: docker-compose build
3. Run the Docker container: docker-compose up -d
4. Access the application in your browser at http://localhost:8000

## Included Tools

- PHP: The programming language used to build the application.
- Composer: A package manager for PHP that makes it easy to manage dependencies.

xdebug is by default disabled. You can enable it by uncoment xdebug section in dockerfile. Installing of xdebug will
remove JIT compiler.

## Contributing

If you would like to contribute to this project, please fork the repository and create a pull request. We welcome all
contributions, including bug fixes, new features, and documentation improvements.

## License

This project is licensed under the terms of the MIT license.