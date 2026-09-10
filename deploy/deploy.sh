#!/bin/bash
# Despliega (o revierte) la app.
#
# Uso normal:      ./deploy.sh                 -> toma origin/main
# Revertir a algo:  ./deploy.sh <commit-o-tag>  -> deploya ese punto exacto
#
# Siempre hace un backup de la base ANTES de correr migraciones, así que
# un deploy que sale mal se puede deshacer en dos pasos:
#   1. ./deploy.sh <commit-anterior>   (vuelve el código)
#   2. restaurar el .sql.gz de deploy/backups si además hubo que revertir datos
set -euo pipefail

REF="${1:-origin/main}"

APP_DIR="/opt/gabriela-mistral/app"
DEPLOY_DIR="$APP_DIR/deploy"
STACK_NAME="gabriela-mistral"
IMAGE="gabriela-mistral-app:latest"

cd "$APP_DIR"

echo "==> Trayendo el codigo mas nuevo del repo"
git fetch origin

echo "==> Parandome en: $REF"
git checkout --detach "$REF"

echo "==> Backup de la base antes de tocar nada"
"$DEPLOY_DIR/backup.sh"

echo "==> Build de la imagen"
docker build -t "$IMAGE" -f Dockerfile .

echo "==> Corriendo migraciones"
set -a
source "$DEPLOY_DIR/.env"
set +a

docker run --rm \
    --network easypanel-gabriela-mistral \
    -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 \
    -e DB_DATABASE="$DB_DATABASE" -e DB_USERNAME="$DB_USERNAME" -e DB_PASSWORD="$DB_PASSWORD" \
    -e APP_KEY="$APP_KEY" \
    "$IMAGE" php artisan migrate --force

echo "==> Desplegando el stack"
cd "$DEPLOY_DIR"
docker stack deploy -c docker-stack.yml "$STACK_NAME" --resolve-image never

echo "==> Listo. Estado de los servicios:"
docker stack services "$STACK_NAME"
