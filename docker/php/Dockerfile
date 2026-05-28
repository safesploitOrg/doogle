FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        default-mysql-client \
        git \
        libsqlite3-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-install dom pdo_mysql pdo_sqlite \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN git config --global --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

COPY . .

RUN chown -R www-data:www-data /var/www/html
