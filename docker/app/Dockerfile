FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y libicu-dev libpq-dev libzip-dev unzip git postgresql-client \
    && docker-php-ext-install intl pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
