FROM php:8.4-fpm-alpine

ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN adduser -D -u 1000 -s /bin/sh robbyte

RUN apk update && apk add --no-cache \
    bash \
    curl \
    git \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    icu-dev \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install pdo pdo_mysql mysqli zip intl opcache bcmath && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY build/prod/php.ini /usr/local/etc/php/php.ini
COPY build/prod/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf

USER robbyte

WORKDIR /app/src

COPY ./src .

USER root
RUN chown -R robbyte:www-data /app/src
USER robbyte

EXPOSE 9000

CMD ["php-fpm"]