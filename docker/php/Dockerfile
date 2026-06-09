FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        curl \
        default-mysql-client \
        git \
        libsqlite3-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install dom pdo_mysql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN git config --system --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_HOME=/tmp/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . .

RUN mkdir -p /var/log/doogle /tmp/composer \
    && chown -R www-data:www-data /var/www/html /var/log/doogle /tmp/composer

USER www-data
