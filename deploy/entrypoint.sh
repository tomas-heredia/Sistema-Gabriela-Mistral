#!/bin/bash
set -e

echo "Esperando a que MySQL acepte conexiones..."
until php -r "new PDO('mysql:host=$DB_HOST;port=$DB_PORT', '$DB_USERNAME', '$DB_PASSWORD');" 2>/dev/null; do
    sleep 2
done
echo "MySQL listo."

# Idempotente: no rompe nada si ya existe (ej. tras un redeploy).
php artisan storage:link 2>/dev/null || true

exec "$@"
