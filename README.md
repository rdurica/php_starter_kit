# PHP Starter Kit

[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](http://php.net)
[![Docker](https://img.shields.io/badge/Docker-powered-blue.svg)](https://www.docker.com/)
[![composer](https://img.shields.io/badge/composer-latest-green.svg)](https://getcomposer.org/)

![php8](https://github.com/rdurica/php_starter_kit/assets/16089770/4430bff1-85af-474a-91ac-80f560c923d8)
"PHP Starter Kit" is a blank PHP application template that includes a Docker image pre-configured with PHP-FPM, Nginx(DEV), Composer,
Opcache with JIT, SSL for local developent.

## Overview

The Docker image provides a consistent and reproducible environment, which is particularly useful when working with a
team of developers or deploying to production. With this starter kit, developers can focus on writing code and not worry
about the underlying setup.

The starter kit is built on the latest stable version of PHP and includes all the necessary extensions and libraries to
get you going. This repository is intended to be a starting point for new projects and can be easily customized to fit the specific
needs of your project.

## Getting Started

1. Clone the repository: git clone https://github.com/rdurica/php_starter_kit.git
2. Build the Docker image, ssl certificates: `make init`
4. Access the application in your browser at https://localhost

After initial instalation you can use these commands:
- **make buildimage:** rebuild docker image
- **make up:** Docker compose up -d
- **make down:** Docker compose down
- **make logs:** Show logs
- **make sh:** docker exec -it <app> /bin/bash
- **make manifest app_name=<$name>:** Generate example manifest for k8s. (for example make manifest app_name=app1).

By default nginx pointing to `/src/public` folder.


## Contributing

If you would like to contribute to this project, please fork the repository and create a pull request. We welcome all
contributions, including bug fixes, new features, and documentation improvements.

## License

This project is licensed under the terms of the MIT license.