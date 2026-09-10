#!/bin/bash
# Punto de entrada del deploy. Deliberadamente chico y estable: lo unico
# que hace es traer el codigo y delegar en run-deploy.sh.
#
# Uso normal:      ./deploy.sh                 -> toma origin/main
# Revertir a algo:  ./deploy.sh <commit-o-tag>  -> deploya ese punto exacto
#
# Por que esta separado de run-deploy.sh: este script vive en el mismo
# repo que actualiza con "git checkout" -- si hiciera todo el trabajo aca
# mismo, ese checkout reescribe el archivo mientras bash todavia lo esta
# interpretando, y termina corriendo una mezcla rara de version vieja y
# nueva (nos paso de verdad: un fix ya pusheado no se aplicaba porque el
# proceso seguia ejecutando la logica que tenia cargada desde antes del
# checkout). "exec" a un archivo DISTINTO lo lee recien del disco, ya con
# el checkout aplicado -- sin ese riesgo.
set -euo pipefail

REF="${1:-origin/main}"
APP_DIR="$HOME/gabriela-mistral/app"

cd "$APP_DIR"

echo "==> Trayendo el codigo mas nuevo del repo"
git fetch origin

echo "==> Parandome en: $REF"
git checkout --detach "$REF"

exec "$APP_DIR/deploy/run-deploy.sh"
