FROM php:8.5-fpm-alpine AS base

ENV TERM=xterm-256color
ENV TZ=Europe/Prague
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apk update && apk add --no-cache \
    supervisor \
    nginx \
    bash \
    curl \
    libpq-dev \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    icu-dev \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql zip intl bcmath && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app/src

FROM base AS vendor

ENV APP_ENV=prod
ENV APP_DEBUG=0

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-scripts --no-interaction --no-autoloader

COPY src .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

FROM base AS runtime

ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN adduser -D -u 1000 -s /bin/sh robbyte

COPY build/prod/php/php.ini /usr/local/etc/php/php.ini
COPY build/prod/php/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf
COPY build/prod/nginx/nginx.conf /etc/nginx/nginx.conf
COPY build/prod/nginx/default.conf /etc/nginx/http.d/default.conf
COPY build/prod/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY build/prod/supervisord/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh && \
    addgroup robbyte nginx && \
    mkdir -p /var/lib/nginx && \
    mkdir -p var/cache var/log && \
    chown -R robbyte:robbyte /app && \
    chown -R robbyte:robbyte /var/log/nginx && \
    chown -R robbyte:robbyte /var/lib/nginx && \
    echo 'alias ll="ls -lah"' >> /home/robbyte/.bashrc && \
    echo 'alias c="composer"' >> /home/robbyte/.bashrc && \
    echo "PS1='\e[92m\u\e[0m@\e[94m\h\e[0m:\e[35m\w\e[0m# '" >> /home/robbyte/.bashrc

WORKDIR /app/src
COPY --from=vendor /app/src /app/src

RUN chown -R robbyte:robbyte /app/src
USER robbyte

EXPOSE 80

CMD ["/usr/local/bin/start.sh", "-c", "/etc/supervisor/conf.d/supervisord.conf"]