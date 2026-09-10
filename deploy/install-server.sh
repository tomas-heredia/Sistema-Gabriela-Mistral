#!/bin/bash
# Preparacion UNICA del servidor. Se corre una sola vez, la primera vez.
set -euo pipefail

BASE_DIR="$HOME/gabriela-mistral"
REPO_URL="https://github.com/tomas-heredia/Sistema-Gabriela-Mistral.git"

mkdir -p "$BASE_DIR/backups"

if [ ! -d "$BASE_DIR/app" ]; then
    git clone "$REPO_URL" "$BASE_DIR/app"
fi

chmod +x "$BASE_DIR/app/deploy/"*.sh

if [ ! -f "$BASE_DIR/app/deploy/.env" ]; then
    cp "$BASE_DIR/app/deploy/.env.stack.example" "$BASE_DIR/app/deploy/.env"
    echo "!! Faltan completar los secretos en $BASE_DIR/app/deploy/.env antes de desplegar."
fi

# Backup diario a las 3am, ademas del que ya se hace antes de cada deploy.
CRON_LINE="0 3 * * * $BASE_DIR/app/deploy/backup.sh >> $BASE_DIR/backups/cron.log 2>&1"
( crontab -l 2>/dev/null | grep -vF "$BASE_DIR/app/deploy/backup.sh" ; echo "$CRON_LINE" ) | crontab -

echo "Listo. Completar $BASE_DIR/app/deploy/.env y despues correr deploy.sh"
