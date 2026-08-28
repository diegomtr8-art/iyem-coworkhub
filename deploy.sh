#!/usr/bin/env bash
#
# Pasos de servidor para prueba.nodico.com.mx (Hostinger, hosting compartido).
#
# IMPORTANTE: este host NO tiene Node/npm, así que los assets se compilan en
# local (`npm run build`) y se suben ya construidos. La sincronización de
# archivos la hace `deploy_prueba.py` por SFTP; este script cubre únicamente
# lo que se ejecuta *dentro* del servidor, y `deploy_prueba.py` lo invoca.
#
# Uso manual:  ssh -i ~/.ssh/id_deploy -p 65002 u489236361@195.35.38.222 'bash -s' < deploy.sh
#
set -euo pipefail

RAIZ="/home/u489236361/domains/prueba.nodico.com.mx/public_html"
cd "$RAIZ"

echo "==> Dependencias de PHP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migraciones"
php artisan migrate --force

echo "==> Contenido público del sitio (idempotente)"
php artisan db:seed --class=NodicoWebSeeder --force

echo "==> robots.txt lo sirve Laravel según APP_ENV; se retira el estático"
rm -f "$RAIZ/robots.txt"

echo "==> Recalentando cachés"
# Siempre limpiar antes de cachear: una caché vieja con un .env nuevo deja el
# sitio en HTTP 500 sin rastro útil en el log.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Listo. Verifica con: curl -sI https://prueba.nodico.com.mx/"
