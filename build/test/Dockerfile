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

RUN docker-php-ext-install pdo pdo_mysql mysqli zip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY build/test/php.ini /usr/local/etc/php/php.ini
COPY build/test/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

USER robbyte
WORKDIR /app/src

COPY ./src .

EXPOSE 9000

CMD ["php-fpm"]