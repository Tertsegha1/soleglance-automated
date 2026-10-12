# Production image: PHP-FPM serving the Laravel app. Used by the app, queue,
# scheduler and reverb services in docker-compose.yml.

FROM node:22-alpine AS assets
# Public WebSocket settings compiled into the browser bundle.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8000
ARG VITE_REVERB_SCHEME=http
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY VITE_REVERB_HOST=$VITE_REVERB_HOST     VITE_REVERB_PORT=$VITE_REVERB_PORT VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.4-fpm AS app
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd intl pcntl bcmath exif opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/soleglance.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-soleglance.conf

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
COPY --from=assets /build/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && ln -sfn /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]

# Static front end: nginx with the compiled public/ directory.
FROM nginx:1.27-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
