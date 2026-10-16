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

# Copia cifrada fuera del servidor (Google Drive, vía un remote "crypt" de
# rclone) -- si el VPS se pierde o se compromete, el backup local se pierde
# con él. No se borra nada del lado remoto: el tamaño de estos dumps es
# insignificante frente al espacio disponible, así que no vale el riesgo de
# un bug en la lógica de borrado remoto.
RCLONE="$HOME/bin/rclone"
if [ -x "$RCLONE" ] && "$RCLONE" listremotes 2>/dev/null | grep -qx "gdrive-crypt:"; then
    if "$RCLONE" copy "$FILE" gdrive-crypt: >/dev/null 2>&1; then
        echo "Copia cifrada subida a Google Drive."
    else
        echo "ADVERTENCIA: fallo al subir la copia cifrada a Google Drive." >&2
    fi
fi
