#!/bin/bash
# El trabajo real del deploy. deploy.sh ya dejo el repo parado en el
# commit correcto antes de llegar aca (ver el comentario en deploy.sh
# sobre por que estan separados).
#
# Siempre hace un backup de la base ANTES de correr migraciones, así que
# un deploy que sale mal se puede deshacer en dos pasos:
#   1. ./deploy.sh <commit-anterior>   (vuelve el código y re-deploya)
#   2. restaurar el .sql.gz de deploy/backups si además hubo que revertir datos
set -euo pipefail

APP_DIR="$HOME/gabriela-mistral/app"
DEPLOY_DIR="$APP_DIR/deploy"
STACK_NAME="gabriela-mistral"

cd "$APP_DIR"

# Taggear con el commit exacto (no ":latest") es lo que hace que Swarm se
# de cuenta de que hay una imagen nueva -- con un tag fijo que se repite,
# "docker stack deploy" ve el mismo nombre de imagen que ya tenia y no
# reinicia los contenedores, aunque el contenido de adentro haya cambiado.
IMAGE_TAG=$(git rev-parse --short HEAD)
IMAGE="gabriela-mistral-app:$IMAGE_TAG"
export APP_IMAGE_TAG="$IMAGE_TAG"

echo "==> Backup de la base antes de tocar nada"
"$DEPLOY_DIR/backup.sh"

echo "==> Build de la imagen ($IMAGE)"
docker build -t "$IMAGE" -f Dockerfile .

set -a
source "$DEPLOY_DIR/.env"
set +a

# "docker stack deploy" (a diferencia de "docker compose") no resuelve de
# forma confiable ${VARIABLES} contra el entorno -- se vio en vivo: tiro
# "invalid reference format" porque APP_IMAGE_TAG nunca se sustituyo. Se
# resuelve el archivo a mano con envsubst antes de pasarselo a Docker, asi
# no dependemos de ese comportamiento.
echo "==> Desplegando el stack"
cd "$DEPLOY_DIR"
envsubst '$APP_NAME $APP_KEY $APP_URL $APP_IMAGE_TAG $DB_DATABASE $DB_USERNAME $DB_PASSWORD $MYSQL_ROOT_PASSWORD' \
    < docker-stack.yml > /tmp/gabriela-mistral-stack.resolved.yml
docker stack deploy -c /tmp/gabriela-mistral-stack.resolved.yml "$STACK_NAME" --resolve-image never
rm -f /tmp/gabriela-mistral-stack.resolved.yml

# El stack (mysql incluido) tiene que existir ANTES de migrar -- en el
# primer deploy no hay ningun "mysql" al que conectarse todavia. La app
# tambien puede tardar un ratito en responder bien hasta que termina de
# migrar (nada raro en un despliegue chico como este).
echo "==> Corriendo migraciones (espera a que mysql este listo)"
docker run --rm \
    --network easypanel-gabriela-mistral \
    -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 \
    -e DB_DATABASE="$DB_DATABASE" -e DB_USERNAME="$DB_USERNAME" -e DB_PASSWORD="$DB_PASSWORD" \
    -e APP_KEY="$APP_KEY" \
    "$IMAGE" php artisan migrate --force

echo "==> Listo. Estado de los servicios:"
docker stack services "$STACK_NAME"

# Se queda con las ultimas 5 imagenes de la app -- suficiente margen para
# rollback sin acumular basura en el disco para siempre.
docker images "gabriela-mistral-app" --format '{{.Tag}} {{.ID}}' \
    | tail -n +6 \
    | awk '{print $2}' \
    | xargs -r docker rmi 2>/dev/null || true
