FROM php:8.2-apache

# set timezone
ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# update and install required packages, clean
RUN apt-get -y update && apt-get -y upgrade && apt-get -y install \
    curl \
    zip \
    git \
    zlib1g-dev \
    libzip-dev \
RUN apt-get -y clean && autoclean && autoremove

# xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

#composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# apache mods and config
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod expires
COPY build/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /app