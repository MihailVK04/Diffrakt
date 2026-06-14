FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx supervisor zlib1g-dev libpng-dev \
    libjpeg62-turbo-dev libwebp-dev unzip curl \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock /var/www/diffrakt/
WORKDIR /var/www/diffrakt
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY public/ /var/www/diffrakt/public/
COPY src/ /var/www/diffrakt/src/

COPY deploy/nginx.conf /etc/nginx/sites-available/default
COPY deploy/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data /var/www/diffrakt

RUN mkdir -p /run/php && chown www-data:www-data /run/php

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]