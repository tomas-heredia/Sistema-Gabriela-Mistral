#!/bin/bash
# Vuelca la base a un .sql.gz con fecha. Se queda con los ultimos 14.
# Si el contenedor de mysql todavia no existe (primer deploy), no hace nada.
set -euo pipefail

DEPLOY_DIR="$HOME/gabriela-mistral/app/deploy"
BACKUP_DIR="$HOME/gabriela-mistral/backups"
STACK_NAME="gabriela-mistral"
KEEP=14

mkdir -p "$BACKUP_DIR"

set -a
source "$DEPLOY_DIR/.env"
set +a

CONTAINER_ID=$(docker ps --filter "name=${STACK_NAME}_mysql" --format '{{.ID}}' | head -n1)

if [ -z "$CONTAINER_ID" ]; then
    echo "Sin contenedor de mysql corriendo todavia -- nada que respaldar."
    exit 0
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILE="$BACKUP_DIR/${DB_DATABASE}_${TIMESTAMP}.sql.gz"

docker exec "$CONTAINER_ID" sh -c "exec mysqldump -uroot -p'${MYSQL_ROOT_PASSWORD}' '${DB_DATABASE}'" | gzip > "$FILE"

echo "Backup guardado en $FILE"

ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm --
