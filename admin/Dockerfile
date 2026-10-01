FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libxml2-dev libpq-dev unzip \
    && docker-php-ext-install dom pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Court gallery: up to 10 photos of 5 MB per request (validated in CourtController).
RUN printf 'upload_max_filesize=8M\npost_max_size=64M\n' > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .

EXPOSE 8001

# php -S instead of "artisan serve": artisan serve drops container env vars (DB_HOST=db) in its workers.

# Waits for the API container (healthy = its migrations ran), then adds the permission tables and seeds roles.
CMD ["sh", "-c", "[ -f .env ] || cp .env.example .env; composer install --no-interaction --prefer-dist --no-progress && (grep -q '^APP_KEY=base64' .env || php artisan key:generate --force) && php artisan optimize:clear && php artisan migrate --force && php artisan db:seed --force && cd public && php -S 0.0.0.0:8001 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
