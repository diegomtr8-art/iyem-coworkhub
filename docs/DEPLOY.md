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

## Lo que el despliegue comprueba solo

`deploy_prueba.py` se niega a desplegar si algo no cuadra, antes y después.

### Antes: el árbol de git tiene que estar limpio (OPS-01)

Desplegar con cambios sin commitear deja el servidor en un estado que no
corresponde a ningún commit, y después no hay forma de saber qué se publicó ni
de reproducirlo. El script lo comprueba **antes de conectarse**:

```
--> Estado del repositorio
ERROR: el arbol de git esta sucio. Commitea o descarta antes de desplegar,
porque si no lo que quede en el servidor no correspondera a ningun commit:

    M deploy_prueba.py

Si es una urgencia: python deploy_prueba.py --sucio
```

`--sucio` existe para una urgencia, pero no la esconde: el sello queda marcado
y se ve publicado.

### El sello de versión

Cada despliegue escribe `public/build/version.json` con la rama, el commit, si
el árbol estaba sucio y la fecha. Para saber qué hay publicado no hay que
suponer nada, se le pregunta al servidor:

```bash
curl -s https://prueba.nodico.com.mx/build/version.json
```

### Después: nada interno abierto, nada público roto

Ver la sección del document root: se piden por HTTP las rutas de
`COMPROBAR_CERRADAS` (deben dar 403/404) y las de `COMPROBAR_ABIERTAS` (deben
dar 200). Blindar de más es tan roto como blindar de menos.

### Después: los dos manifiestos y un asset de verdad (OPS-02)

Este host guarda los assets en dos sitios y ambos tienen que coincidir:

- `public_html/build/` — lo que se sirve por HTTP
- `public_html/public/build/` — lo que lee `public_path('build/manifest.json')`

Si se desincronizan, Laravel apunta a archivos que no existen y **el sitio se
queda sin estilos sin dar ningún error**. El script compara el md5 de los dos
contra el local, y como el manifiesto puede estar bien y el asset no haberse
subido, además pide el `app-*.js` real por HTTP y comprueba que
`/build/version.json` trae el commit de este despliegue y no el del anterior.

```
--> Comprobando los dos manifiestos y un asset publicado
    local                       6a1f...
    ok  build/manifest.json         6a1f...
    ok  public/build/manifest.json  6a1f...
    ok  /build/assets/app-XXXX.js -> 200, 277 KB
    ok  /build/version.json -> a1b2c3d4e5f6
```

## El document root expone la aplicación entera

Lo encontró el `/security-review` del 29 de agosto de 2026 y **es el riesgo más
serio de este montaje.**

`public_html/` es a la vez el document root y la raíz de la aplicación. La regla
de Laravel sólo entrega la petición a `index.php` cuando el archivo pedido **no
existe** en disco (`RewriteCond %{REQUEST_FILENAME} !-f`), de modo que todo lo
que se sincroniza ahí queda servido tal cual, saltandose el framework.

Comprobado en vivo contra `prueba.nodico.com.mx` antes de corregirlo:

| Petición | Antes |
|---|---|
| `/storage/logs/laravel.log` | **200, 67 KB** — log de la aplicación |
| `/composer.lock` | **200, 320 KB** — versión exacta de cada dependencia |
| `/vendor/composer/installed.json` | **200, 241 KB** |
| `/app/Http/Controllers/WelcomeController.php` | **200** — `Fatal error` con la ruta absoluta del servidor |
| `/config/nodico.php` | **200** |
| `/package.json`, `/tailwind.config.js` | **200**, servidos literalmente |
| `/.env` | 403 |

El log importa más de lo que parece: `ContactoController::store` escribe ahí los
fallos de correo, y un `QueryException` al insertar en `contactos` dejaría en el
log el SQL **con los valores**, es decir el nombre, correo, teléfono y mensaje de
quien escribió por el formulario.

Que `/.env` dé 403 es una regla del hosting, no algo que controle este repositorio.

### Lo que hace ahora el despliegue

1. `deploy_prueba.py` deja un `.htaccess` que niega todo en cada carpeta de
   aplicación (`app`, `bootstrap`, `config`, `database`, `resources`, `routes`,
   `storage`, `vendor`, `tests`, `tools`). El contenido está en
   `deploy/htaccess-negar-todo`.
2. `deploy.sh` borra `package.json` y `tailwind.config.js` del servidor (sin Node
   no pintan nada), pone `display_errors = Off` en `.user.ini` y añade **una sola
   vez**, marcado, un bloque `<FilesMatch>` al `.htaccess` de la raíz para
   `composer.json` y `composer.lock`, que sí tienen que quedarse porque
   `composer install` corre ahí. No toca la regla de reescritura existente.
3. Al terminar, `deploy_prueba.py` pide por HTTP las rutas de
   `COMPROBAR_CERRADAS` y **falla el despliegue** si alguna responde 200.

### Lo que falta, y es la solución de verdad

Apuntar el document root del dominio a `public_html/public/` desde hPanel y dejar
la aplicación un nivel por encima. Eso elimina la clase entera de problema en vez
de taparla regla a regla, y de paso quita la doble sincronización de `build/`.
Mientras siga como está, un cambio en el panel del hosting basta para dejar
`.env` al descubierto.

**Además: hay que rotar lo que haya pasado por el log expuesto y truncar
`storage/logs/laravel.log` en el servidor.**

## Verificación de la llave del host SSH

`deploy_prueba.py` usaba `AutoAddPolicy`, que acepta en silencio cualquier llave
de host en cada ejecución. La llave del host es lo único que autentica al
servidor: sin comprobarla, quien se interponga en la red recibe el código
completo de la aplicación y la secuencia de despliegue.

Ahora se confia en `~/.ssh/known_hosts` y se rechaza lo desconocido. La primera
vez desde una máquina nueva, tras verificar la huella en hPanel:

```bash
ssh-keyscan -p 65002 195.35.38.222 >> ~/.ssh/known_hosts
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

**Producción no existe todavía como destino desplegable.** Comprobado el
2026-08-29 contra la API de Hostinger y el DNS público, porque este documento
decía antes «el dominio principal sigue en Odoo» y eso es solo media verdad:

| Qué | Estado real |
|---|---|
| `nodico.com.mx` (raíz) | **Sin registro `A` ni `CNAME`.** No resuelve a nada. |
| `www.nodico.com.mx` | `CNAME` → `iyem.odoo.com`. Esto es lo que sirve Odoo. |
| Vhost en Hostinger | Existe: `/home/u489236361/domains/nodico.com.mx/public_html` |
| Su contenido | Una página estática de obra (`default.php`, conos, de 2025). Sin `artisan`, sin `.env`. |

O sea: Odoo sirve **`www`**, no la raíz, y el directorio de Hostinger no tiene
aplicación ninguna. Publicar los portales **no es un despliegue**: son cuatro
decisiones y luego un despliegue.

1. **Con qué nombre se publica.** Si es `nodico.com.mx` a secas, hay que crear
   el registro `A` de la raíz apuntando a Hostinger, y eso no toca a Odoo. Si
   además se quiere `www`, hay que quitarle el CNAME, y eso **tira el sitio
   público actual**. La tercera vía es un subdominio (`portal.nodico.com.mx`),
   que deja Odoo intacto y es reversible.
2. **La base de datos de producción**, que no existe. Hay que crearla y escribir
   el `.env` con `APP_ENV=production` y `APP_DEBUG=false`.
3. **Qué se hace con los datos de staging:** no se migran, son de prueba.
4. **La línea de cron**, con el dominio nuevo (ver más abajo).

`APP_ENV=production` es además lo que retira el `noindex`, el `Disallow: /` y el
distintivo de ambiente de prueba. Mientras el `.env` diga otra cosa el sitio
queda invisible para los buscadores aunque esté servido correctamente.

## Primer despliegue a un destino nuevo

`deploy_prueba.py` sube código; no configura un servidor. Contra un document
root sin `.env` se detiene **antes** de subir nada y lo dice, porque subir el
código primero deja el sitio roto hasta que alguien cree el archivo a mano.

```bash
# 1. Base de datos y usuario (hPanel o API), y el .env en el document root:
#    APP_ENV=production, APP_DEBUG=false, APP_URL con el dominio real.
# 2. Dependencias de PHP en el servidor (no hay Node; los assets van compilados):
ssh … "cd ~/domains/DOMINIO/public_html && composer install --no-dev -o"
# 3. Esquema:
ssh … "cd ~/domains/DOMINIO/public_html && php artisan migrate --force"
# 4. Siembra del libro de horas. MIRAR LOS NÚMEROS ANTES DE ESCRIBIR:
ssh … "cd ~/domains/DOMINIO/public_html && php artisan nodico:sembrar-libro-horas --simular"
ssh … "cd ~/domains/DOMINIO/public_html && php artisan nodico:sembrar-libro-horas --solo-si-falta"
# 5. Y entonces sí, el código:
npm run build
python deploy_prueba.py --dominio DOMINIO --si-produccion
```

El paso 4 es el que se paga caro si se hace al revés: sin sembrar, la primera
consulta al libro devuelve cero para todo el mundo y cada miembro se encuentra
la bolsa llena. Con miembros reales eso es un regalo de horas que luego hay que
deshacer uno por uno.

---

# Tareas programadas (Fase 1)

Tres tareas del motor de bolsas **tienen que correr en el servidor**. Sin ellas
el sistema no falla de forma visible: simplemente las horas no se reinician
nunca y los no-shows no se marcan. Es la peor clase de avería, la que nadie ve.

## La línea de cron en Hostinger

En hPanel → *Avanzado* → *Cron Jobs*, una sola entrada **cada minuto**. Laravel
decide por su cuenta qué toca ejecutar en cada pasada; no hay que dar de alta
una entrada por comando.

**La que está puesta en staging**, creada el 01/09/2026 (uid `1D9AJDprHu`):

```
* * * * * /usr/bin/php /home/u489236361/domains/prueba.nodico.com.mx/public_html/artisan schedule:run >> /home/u489236361/domains/prueba.nodico.com.mx/public_html/storage/logs/cron.log 2>&1
```

Tres detalles que importan y que se descubren solo cuando no funciona:

- **Rutas absolutas, no `cd …&&`.** El cron de Hostinger no arranca en el
  directorio del proyecto, y encadenar un `cd` es una fuente de fallos
  silenciosos: si falla, el `artisan` no se encuentra y nadie se entera.
- **`/usr/bin/php`** es la 8.2.31 en este host, comprobado con `which php`. Si
  algún día la 8.2 se mueve (`/opt/alt/php82/usr/bin/php`, por ejemplo), esa es
  la que va: con otra versión el comando corre igual, pero contra otro
  `vendor/` y otra configuración.
- **El log va a un archivo, no a `/dev/null`.** Es lo único que permite
  comprobar después que la tarea existe de verdad. `schedule:run` apenas
  escribe cuando no hay nada debido, así que no crece solo; si algún día
  molesta, se cambia a `/dev/null` **después** de haber verificado que
  funciona, nunca antes.

Para producción, la misma línea cambiando el dominio.

Antes de crearla en otro host, comprueba dónde vive la 8.2:

```bash
ssh USUARIO@HOST 'which php83 php82 php; php -v'
```

## Qué corre y cuándo

| Comando | Cuándo | Qué pasa si no corre |
|---|---|---|
| `nodico:reiniciar-ciclos` | 00:05 diario | Las bolsas no se reinician: al segundo mes nadie puede reservar nada |
| `nodico:marcar-no-show` | cada 30 min | Los plantones quedan como «Confirmada» y la tasa de no-show sale siempre en cero |
| `nodico:reconstruir-saldos` | 03:00 diario | Nadie se entera si los contadores se separan del libro |

Las tres son idempotentes: repetirlas no duplica nada. `reiniciar-ciclos` acepta
`--desde=AAAA-MM-DD` para recuperar días en que el servidor estuvo caído.

## Verificar que corre de verdad

**Una tarea programada que nadie comprueba es una tarea que no existe.** Al
terminar el despliegue, y no antes de darlo por bueno:

```bash
R=~/domains/prueba.nodico.com.mx/public_html

# 1. El planificador ve las tres tareas
ssh … "cd $R && php artisan schedule:list"

# 2. Cada comando corre a mano sin reventar (--simular no escribe nada)
ssh … "cd $R && php artisan nodico:reiniciar-ciclos --simular"
ssh … "cd $R && php artisan nodico:marcar-no-show --simular"

# 3. El cron arranca: cron.log existe
ssh … "ls -l $R/storage/logs/cron.log"

# 4. LO QUE DE VERDAD IMPORTA: el cron ejecuta tareas.
#    `marcar-no-show` corre en :00 y :30, así que a la media hora como mucho
#    tiene que haber una línea aquí.
ssh … "cat $R/storage/logs/tareas.log"
```

### Por qué hay dos logs, y por qué el que cuenta es el segundo

`cron.log` recoge la salida de **`schedule:run`**. `tareas.log` recoge la de
**los comandos que `schedule:run` ejecuta**, vía `appendOutputTo` en
`routes/console.php`.

No son lo mismo, y confundirlos cuesta una tarde: **`schedule:run` no vuelca a
su salida lo que imprimen los comandos que lanza**. Los corre y descarta su
salida. Comprobado aquí el 01/09/2026 — el cron llevaba diez minutos
funcionando perfectamente y `cron.log` seguía en **cero bytes**, con lo que era
imposible distinguir «corrió y no había nada que hacer» de «el cron no existe».

Así que:

| Archivo | Qué prueba |
|---|---|
| `cron.log` existe | Hostinger está llamando a `schedule:run`. Puede estar vacío y estar todo bien. |
| `tareas.log` tiene líneas | Una tarea **se ejecutó de verdad**. Esto es lo que hay que ver antes de dar el despliegue por bueno. |

Si a los 35 minutos `tareas.log` no tiene nada, algo falla: mira la entrada en
hPanel, la ruta de PHP y que `CACHE_STORE` soporte locks (`onOneServer` los
necesita; `database` y `redis` valen, `array` no).

## Migración del libro de horas

Antes de que los portales entren en producción, los contadores que ya existen
tienen que pasar al libro. Si no, la primera consulta devuelve cero para todo el
mundo y **cada miembro se encuentra la bolsa llena**: un regalo de horas el día
del despliegue.

```bash
# Primero en seco: dice qué escribiría, sin tocar nada
php artisan nodico:sembrar-libro-horas --simular

# Y luego de verdad. Compara los saldos antes y después y se revierte solo
# si no cuadran.
php artisan nodico:sembrar-libro-horas

# Comprobación independiente
php artisan nodico:reconstruir-saldos
```

Es reversible con `--revertir`. Hazlo **con el sitio en mantenimiento**: si
alguien reserva a mitad de la siembra, la foto de antes y la de después no
cuadran y el comando se revierte solo, que es lo correcto pero obliga a repetir.
