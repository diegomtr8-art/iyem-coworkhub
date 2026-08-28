# Despliegue a prueba.nodico.com.mx (staging)

Ambiente de pruebas del sitio público de Nódico, en Hostinger (hosting compartido).

## Datos del servidor

| Dato | Valor |
|---|---|
| SSH | `ssh -i ~/.ssh/id_deploy -p 65002 u489236361@195.35.38.222` |
| Document root | `/home/u489236361/domains/prueba.nodico.com.mx/public_html` |
| PHP (CLI) | 8.2.31 |
| PHP (web, PHP-FPM) | 8.3.31 |
| Node / npm | **no disponible** — los assets se compilan en local |
| composer, git | disponibles |

## Layout del servidor (no es el estándar de Laravel)

`public_html/` **es** el document root y contiene la aplicación completa:
`app/`, `vendor/`, `bootstrap/`, `.env` y un `index.php` propio cuyas rutas
**no** llevan `../`. Nunca se coloca nada en la raíz del dominio
(`/home/u489236361/domains/prueba.nodico.com.mx/`): ahí hay un archivo marcador
`DO_NOT_UPLOAD_HERE`.

Consecuencias prácticas:

- Los estáticos (`fonts/`, `icons/`, `img/`, `favicon.ico`) van a la **raíz** de
  `public_html/`, no dentro de `public_html/public/`.
- Los assets compilados van a **dos** rutas y ambas deben quedar sincronizadas:
  - `public_html/build/` — lo que se sirve por HTTP (`/build/assets/...`).
  - `public_html/public/build/` — lo que lee `public_path('build/manifest.json')`
    al renderizar. Si sólo se actualiza una, el sitio sirve assets viejos o rotos.

## Procedimiento

```bash
# 1. En local: compilar los assets (el servidor no tiene Node)
cd C:\xampp\htdocs\coworkhub
npm run build

# 2. Subir archivos y ejecutar los pasos de servidor
python deploy_prueba.py
```

`deploy_prueba.py` sincroniza por SFTP `app/ bootstrap/ config/ database/ routes/
resources/`, los assets, y luego ejecuta `deploy.sh` dentro del servidor
(composer install, `migrate --force`, `db:seed --class=NodicoWebSeeder`, borrado
del `robots.txt` estático y recalentado de cachés).

### Verificación posterior

```bash
curl -sI https://prueba.nodico.com.mx/            # debe dar 200
curl -s  https://prueba.nodico.com.mx/robots.txt  # debe decir "Disallow: /"
for r in / /nosotros /membresias /eventos /actividades; do
  echo "$r -> $(curl -s -o /dev/null -w '%{http_code}' https://prueba.nodico.com.mx$r)"
done
```

**El éxito de `php artisan` por SSH no confirma que la web funcione**: el CLI usa
PHP 8.2 y el sitio corre sobre PHP-FPM 8.3. Siempre confirma con `curl` contra la
URL real.

## Configuración de staging (`.env` en el servidor)

El `.env` vive **sólo** en el servidor (`public_html/.env`) y nunca se sube al
repositorio. Debe contener:

```ini
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://prueba.nodico.com.mx
```

`APP_ENV=staging` es lo que activa las tres protecciones del ambiente de prueba:

1. `robots.txt` responde `Disallow: /` (ruta dinámica en `routes/web.php`).
2. `app.blade.php` emite `<meta name="robots" content="noindex, nofollow">`.
3. Se muestra el distintivo «Ambiente de prueba» (`StagingBanner.vue`).

Credenciales de base de datos y de correo (`contacto@nodico.com.mx` vía
`mail.nodico.com.mx:587`, TLS) ya están provisionadas en ese archivo. Antes de
modificarlo, léelo: **no lo sobrescribas a ciegas**.

## DNS y certificado

- Registros `A` para `prueba.nodico.com.mx` y `www.prueba.nodico.com.mx`
  apuntando a `195.35.38.222`. Ya están dados de alta y resolviendo.
- El virtual host lo administra hPanel; el document root es `public_html/`.
- Certificado SSL emitido por Hostinger (Let's Encrypt) para ambos nombres.
  Si se agrega un nombre nuevo, reemitirlo desde hPanel → SSL.

## Permisos

`storage/` y `bootstrap/cache/` deben ser escribibles por el usuario del servidor
web:

```bash
chmod -R 775 public_html/storage public_html/bootstrap/cache
```

## Problemas conocidos

**HTTP 500 con log vacío tras sincronizar archivos.** Caché de configuración
rancia. `deploy.sh` ya limpia antes de cachear; si aparece igualmente:

```bash
cd /home/u489236361/domains/prueba.nodico.com.mx/public_html
php artisan config:clear && php artisan route:clear && php artisan view:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**`php artisan storage:link` falla** con `Call to undefined function exec()`:
Hostinger deshabilita `exec()`. Si llega a hacer falta, crear el enlace a mano:

```bash
ln -s ../storage/app/public public_html/public/storage
```

**Imágenes o iconos rotos tras un deploy de frontend.** Los estáticos nuevos no
se subieron a la raíz de `public_html/`. Comprobar con
`ls public_html/img/nodico | head` y volver a correr `deploy_prueba.py`.

## Paso a producción (nodico.com.mx)

Hoy el dominio principal sigue en Odoo. Cuando se migre, además de repuntar el
DNS hay que poner `APP_ENV=production` para que se retire el `noindex`, el
`Disallow: /` y el distintivo de ambiente de prueba.
