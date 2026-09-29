FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libxml2-dev libpq-dev unzip \
    && docker-php-ext-install dom pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .

EXPOSE 8000

CMD ["sh", "-c", "composer install --no-interaction --prefer-dist --no-progress && php artisan optimize:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]