#!/bin/sh
set -e

if [ "$1" = "php-fpm" ]; then
    if [ ! -f vendor/autoload_runtime.php ]; then
        composer install --no-interaction --prefer-dist
    fi

    # Chaves do JWT (LexikJWTAuthenticationBundle)
    if [ ! -f config/jwt/private.pem ]; then
        php bin/console lexik:jwt:generate-keypair --skip-if-exists
    fi

    # Aguarda o Postgres e aplica migrations
    until php bin/console dbal:run-sql -q "SELECT 1" > /dev/null 2>&1; do
        echo "Aguardando banco de dados..."
        sleep 2
    done
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    if [ "${LOAD_FIXTURES:-0}" = "1" ] && [ "$(php bin/console dbal:run-sql 'SELECT COUNT(*) FROM funcionario' 2>/dev/null | grep -Eo '[0-9]+' | tail -1)" = "0" ]; then
        php bin/console doctrine:fixtures:load --no-interaction --purge-with-truncate
    fi

    php bin/console tailwind:build || true
fi

exec docker-php-entrypoint "$@"
