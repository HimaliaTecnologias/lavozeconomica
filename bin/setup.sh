#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/.."

# Puertos del .env (19090 / 3309 / 6382). Docker Compose también los lee de ahí.
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  . ./.env
  set +a
fi

APP_PORT="${APP_PORT:-19140}"
MYSQL_PORT="${MYSQL_PORT:-3309}"
REDIS_PORT="${REDIS_PORT:-6382}"

echo "==> Levantando servicios..."
mkdir -p public/media var/cache var/log
docker compose down --remove-orphans >/dev/null 2>&1 || true
docker compose up -d --build mysql redis

echo "==> Esperando MySQL..."
sleep 15

echo "==> Instalando dependencias PHP..."
docker compose run --rm php composer install --no-interaction --no-scripts

echo "==> Migrando BD plataforma..."
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Levantando aplicación..."
docker compose up -d

echo ""
echo "Abre el asistente de instalación (momento cero):"
echo "  http://localhost:${APP_PORT}/install"
echo ""
echo "Si ya instalaste antes, la portada está en:"
echo "  http://localhost:${APP_PORT}/"
