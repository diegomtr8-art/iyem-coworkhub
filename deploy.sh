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

echo "==> Cerrando lo que no debe salir por HTTP"
# `public_html` ES el document root, asi que Apache sirve cualquier archivo que
# exista en disco: la regla de Laravel solo entrega la peticion a index.php
# cuando el archivo NO existe. Comprobado el 2026-08-29, antes de esto:
#   /storage/logs/laravel.log        200, 67 KB
#   /composer.lock                   200, 320 KB
#   /vendor/composer/installed.json  200, 241 KB
# Las carpetas de aplicacion las cierra deploy_prueba.py con un .htaccess
# propio en cada una; aqui van los archivos sueltos de la raiz.

# Sin Node en el servidor no pintan nada, y se servian tal cual.
rm -f "$RAIZ/package.json" "$RAIZ/tailwind.config.js"

# display_errors estaba activo: pedir un .php de la app devolvia un Fatal error
# con la ruta absoluta del servidor.
if ! grep -qs 'display_errors' "$RAIZ/.user.ini"; then
    printf 'display_errors = Off\n' >> "$RAIZ/.user.ini"
fi

# composer.json y composer.lock tienen que quedarse, porque `composer install`
# corre en esta carpeta. Se niegan por nombre, una sola vez y marcado, sin
# tocar la regla de reescritura de Laravel que ya vive en este .htaccess.
if [ -f "$RAIZ/.htaccess" ] && ! grep -q 'CoworkHub: archivos internos' "$RAIZ/.htaccess"; then
    cat >> "$RAIZ/.htaccess" <<'FIN_HT'

# --- CoworkHub: archivos internos (no editar a mano) ---
<FilesMatch "^(composer\.(json|lock)|package(-lock)?\.json|.*\.(log|md|sh|py|ya?ml|lock))$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
# --- fin CoworkHub ---
FIN_HT
fi

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
