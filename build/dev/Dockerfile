FROM php:8.4-fpm-alpine

ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apk update && apk add --no-cache \
    curl \
    git \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    icu-dev \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install pdo pdo_mysql mysqli zip intl opcache bcmath && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY build/dev/php.ini /usr/local/etc/php/php.ini
COPY build/dev/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app/src/public
RUN chown -R 1000:1000 /app/src

COPY . .

EXPOSE 9000

CMD ["php-fpm"]