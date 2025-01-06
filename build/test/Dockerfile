FROM php:8.4-fpm-alpine

ENV TERM=xterm-256color
ENV TZ=Europe/Prague
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN adduser -D -u 1000 -s /bin/sh robbyte

RUN apk update && apk add --no-cache \
    supervisor \
    nginx \
    bash \
    curl \
    git \
    libzip-dev \
    zlib-dev \
    libxml2-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-configure gd --with-jpeg --with-webp && \
    docker-php-ext-install gd pdo pdo_mysql mysqli zip intl opcache bcmath && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN curl -sS https://get.symfony.com/cli/installer | bash -s -- --install-dir=/usr/local/bin \
    && mv /usr/local/bin/symfony* /usr/local/bin/symfony \
    && chmod +x /usr/local/bin/symfony

RUN mkdir -p /etc/nginx/certs && \
    openssl req -x509 -newkey rsa:2048 \
    -keyout /etc/nginx/certs/server.key \
    -out /etc/nginx/certs/server.crt \
    -days 3650 -nodes \
    -subj "/C=CZ/ST=Praha/L=Praha/O=LocalOrg/CN=localhost"

COPY build/dev/php/php.ini /usr/local/etc/php/php.ini
COPY build/dev/php/php-fpm.conf /usr/local/etc/php-fpm.d/app.conf
COPY build/dev/nginx/nginx.conf /etc/nginx/nginx.conf
COPY build/dev/nginx/default.conf /etc/nginx/http.d/default.conf
COPY build/dev/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY build/dev/supervisord/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh && \
    addgroup robbyte nginx && \
    mkdir -p /var/lib/nginx && \
    chown -R robbyte:robbyte /var/log/nginx && \
    chown -R robbyte:robbyte /var/lib/nginx && \
    echo 'alias ll="ls -lah"' >> /home/robbyte/.bashrc && \
    echo 'alias c="composer"' >> /home/robbyte/.bashrc && \
    echo "PS1='\e[92m\u\e[0m@\e[94m\h\e[0m:\e[35m\w\e[0m# '" >> /home/robbyte/.bashrc

WORKDIR /app/src
COPY ./src .

RUN chown -R robbyte:robbyte /app/src
USER robbyte

CMD ["/usr/local/bin/start.sh", "-c", "/etc/supervisor/conf.d/supervisord.conf"]