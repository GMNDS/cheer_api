# syntax=docker/dockerfile:1

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY --chown=www-data:www-data composer.json composer.lock ./
COPY --chown=www-data:www-data src ./src

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --classmap-authoritative

COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data routes ./routes
COPY docker/entrypoint.sh /usr/local/bin/cheer-entrypoint

RUN chmod +x /usr/local/bin/cheer-entrypoint

ENTRYPOINT ["cheer-entrypoint"]
CMD ["apache2-foreground"]

EXPOSE 80
