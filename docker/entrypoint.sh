#!/bin/sh
set -e

# Миграции и сидер выполняет только веб-контейнер: если запустить их ещё и в
# воркере, два процесса начнут создавать таблицы одновременно.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    attempts=0

    until php artisan migrate --force --no-interaction; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge 20 ]; then
            echo "База данных недоступна, останавливаюсь"
            exit 1
        fi

        echo "Жду базу данных... (попытка $attempts)"
        sleep 3
    done

    # Сидер идемпотентный: создаёт или обновляет тестового пользователя.
    php artisan db:seed --force --no-interaction
fi

# Кэш конфигурации ускоряет ответы; кэшируем только на проде.
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
