# Runtime image for the SoleGlance web app, queue worker, scheduler and Reverb.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite zip gd intl pcntl bcmath exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
