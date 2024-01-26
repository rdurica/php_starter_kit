FROM php:8.3-apache

ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get -y update && apt-get -y upgrade && apt-get -y install \
    curl \
    zip \
    git \
    zlib1g-dev \
    libzip-dev \
    libicu-dev g++

RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-configure intl && docker-php-ext-install intl && docker-php-ext-install opcache
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get -y clean && apt-get -y autoclean && apt-get -y autoremove

# xdebug
#RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN a2enmod ssl && a2enmod socache_shmcb && a2enmod rewrite

COPY build/dev/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY build/dev/php.ini /usr/local/etc/php/php.ini

WORKDIR /app/src

RUN mkdir log && mkdir temp && mkdir temp/cache && chmod 777 log && chmod -R 777 temp
RUN chown -R 1000:1000 /app/src