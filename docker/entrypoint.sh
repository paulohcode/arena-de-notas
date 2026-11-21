#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "ERRO: APP_KEY não definido no .env" >&2
    exit 1
fi

# Garante a estrutura de storage (o volume pode começar vazio)
mkdir -p storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs

php artisan storage:link --force >/dev/null 2>&1 || true

# Caches de produção (lidos a partir das variáveis de ambiente atuais)
php artisan optimize

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Rodando migrations..."
    php artisan migrate --force --isolated
fi

exec "$@"
