FROM dunglas/frankenphp:1-php8.5-alpine

ENV TERM=xterm-256color
ENV TZ=Europe/Prague

# System deps + PHP extensions + timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && apk add --no-cache \
        tzdata nodejs npm bash curl git openssh-client \
        linux-headers libpq-dev libzip-dev zlib-dev \
        libxml2-dev icu-dev freetype-dev \
        libjpeg-turbo-dev libpng-dev libwebp-dev \
    && install-php-extensions \
        pdo pdo_mysql mysqli pdo_pgsql pgsql \
        zip intl bcmath gd sockets xdebug

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI + Laravel installer
RUN curl -sS https://get.symfony.com/cli/installer | bash -s -- --install-dir=/usr/local/bin \
    && mv /usr/local/bin/symfony* /usr/local/bin/symfony 2>/dev/null || true \
    && chmod +x /usr/local/bin/symfony \
    && composer global require laravel/installer

WORKDIR /app/src

# User setup + aliases
RUN adduser -D -u 1000 -s /bin/sh robbyte \
    && chown -R robbyte:robbyte /data /config \
    && echo 'alias ll="ls -lah"' >> /home/robbyte/.bashrc \
    && echo 'alias c="composer"' >> /home/robbyte/.bashrc \
    && echo 'alias art="php artisan"' >> /home/robbyte/.bashrc \
    && echo 'alias sf="php bin/console"' >> /home/robbyte/.bashrc \
    && echo 'alias laravel="composer create-project laravel/laravel ."' >> /home/robbyte/.bashrc \
    && echo 'alias nette="composer create-project nette/web-project ."' >> /home/robbyte/.bashrc \
    && echo "PS1='\e[92m\u\e[0m@\e[94m\h\e[0m:\e[35m\w\e[0m# '" >> /home/robbyte/.bashrc

USER robbyte

EXPOSE 80 443
COPY --chmod=755 --chown=robbyte:robbyte build/dev/entrypoint.sh /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]
