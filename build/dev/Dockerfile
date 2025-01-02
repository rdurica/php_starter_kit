FROM php:8.4-fpm-alpine

ENV TERM=xterm-256color
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

RUN curl -sS https://get.symfony.com/cli/installer | bash -s -- --install-dir=/usr/local/bin \
    && mv /usr/local/bin/symfony* /usr/local/bin/symfony \
    && chmod +x /usr/local/bin/symfony

COPY build/dev/php.ini /usr/local/etc/php/php.ini
COPY build/dev/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf

RUN echo 'alias ll="ls -lah"' >> /home/robbyte/.bashrc && \
    echo 'alias c="composer"' >> /home/robbyte/.bashrc && \
    echo "PS1='\e[92m\u\e[0m@\e[94m\h\e[0m:\e[35m\w\e[0m# '" >> /home/robbyte/.bashrc \
    chown -R robbyte:robbyte /app/src

USER robbyte
WORKDIR /app/src

COPY ./src .

EXPOSE 9000

CMD ["php-fpm"]