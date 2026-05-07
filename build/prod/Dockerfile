FROM dunglas/frankenphp:1-php8.5-alpine AS base

ENV TERM=xterm-256color
ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apk add --no-cache \
    bash \
    curl \
    netcat-openbsd \
    libpq-dev \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    icu-dev \
    linux-headers \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev

RUN install-php-extensions \
    pdo pdo_mysql mysqli pdo_pgsql pgsql \
    zip intl bcmath gd sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app/src

FROM base AS vendor

COPY src .
RUN if [ -f composer.json ]; then \
        composer install --no-dev --prefer-dist --no-progress --no-scripts --no-interaction --no-autoloader; \
        composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts; \
    else \
        echo "No composer.json found; skipping Composer install"; \
    fi

FROM base AS runtime

RUN adduser -D -u 1000 -s /bin/sh robbyte \
    && chown -R robbyte:robbyte /data /config

COPY build/prod/Caddyfile /etc/caddy/Caddyfile
COPY build/prod/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# Enable PHP production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY build/prod/opcache-jit.ini "$PHP_INI_DIR/conf.d/opcache-jit.ini"

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /app/src
COPY --from=vendor /app/src /app/src

RUN chown -R robbyte:robbyte /app/src
USER robbyte

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

CMD ["/usr/local/bin/docker-entrypoint.sh"]
