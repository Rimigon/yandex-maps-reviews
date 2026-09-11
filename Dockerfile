# Сборка образа приложения: отдельные стадии для PHP-зависимостей и фронтенда,
# чтобы в финальном образе не было composer и node_modules.
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --no-dev --no-scripts --optimize


FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build


FROM php:8.3-cli-alpine AS runtime

RUN apk add --no-cache bash icu-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl opcache

WORKDIR /var/www/html

COPY --from=vendor /app .
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=mysql \
    QUEUE_CONNECTION=database

EXPOSE 8080

ENTRYPOINT ["./docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
