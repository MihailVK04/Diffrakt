FROM php:8.4-fpm

RUN apt-get update && apt-get install -y nginx supervisor zlib1g-dev libpng-dev \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY deploy/nginx.conf /etc/nginx/sites-available/default
COPY deploy/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

COPY public/ /var/www/diffrakt/public/
COPY src/ /var/www/diffrakt/src/

RUN mkdir -p /var/www/diffrakt/storage/originals \
             /var/www/diffrakt/storage/thumbs \
             /var/www/diffrakt/storage/processed \
             /var/www/diffrakt/storage/avatars \
    && chown -R www-data:www-data /var/www/diffrakt

RUN mkdir -p /run/php && chown www-data:www-data /run/php

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]