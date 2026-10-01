# Lanzamiento a producción (nodico.com.mx)

**Estado al 1-oct-2026: producción NO está encendida.** Todo sigue en
`prueba.nodico.com.mx` hasta nuevo aviso. Este documento deja el procedimiento
listo para que el día del lanzamiento no haya que improvisar nada: cada paso
tiene su comprobación, y si la comprobación falla, **se para ahí**.

Archivos relacionados:

- `deploy/env-produccion.ejemplo` — la plantilla del `.env`, con lo que ya se
  sabe escrito y lo que falta marcado como `__PENDIENTE__`.
- `docs/DEPLOY.md` — cómo funciona el despliegue y el servidor.
- `docs/LEGAL-PENDIENTES.md`, `docs/PAGOS-BBVA.md`, `docs/AUTH-PROVEEDORES.md`.

---

## 1. Lo que hace falta de fuera

Sin esto no se lanza. Ninguno lo puede resolver el código.

| # | Qué | Quién lo da | Para qué | Bloquea |
|---|---|---|---|---|
| E1 | **Credenciales de producción de Ecommerce BBVA**: ID de comercio, llave privada, llave pública y número de afiliación de **Nódico** (hoy se usan las de sandbox de «Herencia Viva»). Y la aprobación del banco para pasar a producción. | Ejecutivo de BBVA | Cobrar con tarjeta | Sí, el cobro con tarjeta. Sin ellas se puede lanzar solo con pago por referencia. |
| E2 | **Buzones del dominio**: `contacto@nodico.com.mx` existe y envía (comprobado el 28-sep-2026); falta confirmar que exista `facturacion@nodico.com.mx` (el sitio lo da como contacto de facturas) y, si jurídico lo decide, `privacidad@nodico.com.mx` para derechos ARCO. Y la contraseña de `contacto@` para el `.env`. | Quien administre el correo en Hostinger | Verificación de cuentas, recuperación de contraseña, avisos de pago, formulario | Sí |
| E3 | **Acceso al panel de Hostinger (hPanel)** de la cuenta `u489236361`, o una persona con ese acceso disponible el día. | IYEM | Crear la base de datos, la línea de cron, el SSL y el cambio de raíz del documento | Sí |
| E4 | **Cambio de raíz del documento** de `nodico.com.mx` a `public_html/public` en hPanel (ver §4 y `docs/DEPLOY.md`, «El document root expone la aplicación entera»). | Quien tenga E3 | Que el código y los registros no queden al alcance por HTTP | No (hay alternativa probada, ver §4) |
| E5 | **Decisión del nombre** y el cambio de DNS: hoy la raíz `nodico.com.mx` **no resuelve** y `www.nodico.com.mx` apunta a Odoo (`iyem.odoo.com`). Publicar en la raíz no toca Odoo; publicar también en `www` **apaga el sitio de Odoo**. | Dirección de Nódico / IYEM, y quien administre el DNS | Que el dominio llegue a Hostinger | Sí |
| E6 | **Textos legales revisados** por jurídico, y las tres brechas ⛔ de `docs/LEGAL-PENDIENTES.md` (consentimiento biométrico, fotos del rostro en el servidor, borrado del rostro). | Jurídico del IYEM | Lanzar con aviso de privacidad válido | Sí, mientras se use el reconocimiento facial |
| E7 | La **primera cuenta de administración**: nombre y correo de la persona. | Dirección de Nódico | Operar el panel | Sí |
| E8 | Alguien **en la oficina** para el agente del torno (cambiar su dirección y su secreto). | Personal de Nódico | Que el torno registre en producción | Solo el acceso facial |
| E9 | *(Opcional)* Credenciales de Google Cloud (`docs/AUTH-PROVEEDORES.md`). | Quien administre la cuenta de Google del IYEM | Botón «Continuar con Google» | No |
| E10 | *(Opcional)* Publicar la app móvil apuntando a producción (cuentas de Apple y Google Play). | IYEM | App en las tiendas | No |

---

## 2. Antes del día (se puede hacer días antes sin afectar a nadie)

Ninguno de estos pasos publica nada: la raíz `nodico.com.mx` no resuelve
hasta el paso D9.

**P1. Base de datos.** hPanel → *Bases de datos MySQL*: crear la base y su
usuario.
*Comprobación:* `mysql -u USUARIO -p BASE -e "select 1"` por SSH responde `1`.

**P2. El código en `main`.** Lo que se lance es exactamente lo que se probó:
```bash
curl -s https://prueba.nodico.com.mx/build/version.json   # commit publicado en pruebas
git rev-parse origin/main                                  # tiene que ser el mismo
```
*Comprobación:* los dos hashes coinciden y nadie despliega a pruebas después.

**P3. Comprobar las credenciales de BBVA (E1) sin cobrar nada.** En el servidor,
con las llaves recibidas en variables de entorno de la sesión (no en archivos):
```bash
curl -s -o /dev/null -w "%{http_code}\n" -u "$BBVA_LLAVE_PRIVADA:" \
  "https://api.ecommercebbva.com/v1/$BBVA_MERCHANT_ID/charges?limit=1"
```
*Comprobación:* `200`. Un `401` es llave o comercio equivocado; **no** se
arregla cambiando `PAGOS_PASARELA` (las dos plataformas aceptan las mismas
llaves, ver `docs/PAGOS-BBVA.md`).

**P4. Ensayo del cambio de raíz en pruebas (E4).** Hacer primero el cambio de
raíz en `prueba.nodico.com.mx` y comprobar el sitio. Si funciona, producción se
monta igual desde el primer día. Si no da tiempo, se lanza con el mismo montaje
de pruebas (§4), que está probado y cerrado regla a regla.

---

## 3. El día del lanzamiento

Orden estricto. Las órdenes `ssh …` usan la llave `~/.ssh/id_deploy` y el puerto
65002 (`docs/DEPLOY.md`). `R` es `/home/u489236361/domains/nodico.com.mx/public_html`.

**D1. Respaldo de lo que hay.** El vhost de `nodico.com.mx` tiene hoy una página
de obra.
```bash
ssh … "tar czf ~/respaldos/nodico_com_mx_antes_$(date +%Y%m%d).tgz -C ~/domains/nodico.com.mx public_html"
```
*Comprobación:* el archivo existe y pesa más de 0.

**D2. Árbol limpio de `main`.** Desde un worktree, para no arrastrar archivos de
otras sesiones:
```bash
git fetch && git worktree add --detach ../coworkhub-produccion origin/main
cd ../coworkhub-produccion && npm ci && npm run build
```
*Comprobación:* `git status --short` vacío y existe `public/build/manifest.json`.

**D3. Vaciar el vhost** (solo la página de obra, ya respaldada en D1):
```bash
ssh … "cd R && rm -f default.php index.html && ls -la"
```

**D4. El `.env`.** Copiar `deploy/env-produccion.ejemplo` a `R/.env`, llenar
cada `__PENDIENTE__` y generar cada `__GENERAR__`:
```bash
ssh … "cd R && grep -n '__PENDIENTE\|__GENERAR' .env"
```
*Comprobación:* la orden no imprime **nada**, y `APP_ENV=production`,
`APP_DEBUG=false`, `BBVA_SANDBOX=false`.
`APP_KEY` se genera **una sola vez** y se guarda en el gestor de contraseñas: si
se pierde, se pierden los códigos de doble factor y las credenciales QR.

**D5. Dependencias y esquema** (`deploy_prueba.py` no corre sobre un destino sin
ellas):
```bash
ssh … "cd R && composer install --no-dev -o && php artisan migrate --force && php artisan migrate:status | tail -3"
```
*Comprobación:* todas las migraciones en `Ran`.

**D6. Libro de horas.** Mirar antes de escribir (`docs/DEPLOY.md`):
```bash
ssh … "cd R && php artisan nodico:sembrar-libro-horas --simular && php artisan nodico:sembrar-libro-horas --solo-si-falta"
```
*Comprobación:* sin miembros todavía, la simulación no propone ningún saldo.
Si propone alguno, parar: la base no es la nueva.

**D7. El código.**
```bash
python deploy_prueba.py --dominio nodico.com.mx --si-produccion
```
El script sube, corre `deploy.sh` **en la raíz de producción** (antes del
1-oct-2026 lo corría en la de pruebas), siembra el contenido inicial porque no
hay planes, y **no** toca cuentas demo.
*Comprobación:* termina en `=== Deploy completado` y `version.json` dice el
commit de P2. Que no aparezca `Cuentas de demostración` en la salida.

**D8. Primera cuenta de administración (E7).**
```bash
ssh … "cd R && php artisan nodico:crear-admin correo@dominio 'Nombre Apellido'"
```
*Comprobación:* «Le llegó el enlace»; la persona abre el correo, pone su
contraseña y entra a `/dashboard`. Esto prueba también el correo (E2).
**Nunca** `php artisan db:seed` en producción: se niega a correr, y es lo
correcto.

**D9. DNS y SSL (E5).** Registro `A` de `nodico.com.mx` a la IP del servidor
de Hostinger; en hPanel, SSL para `nodico.com.mx`.
*Comprobación:* `curl -sI https://nodico.com.mx/` → `200` con certificado
válido (puede tardar en propagarse; `nslookup nodico.com.mx 8.8.8.8`).

**D10. Cron (E3).** hPanel → *Cron Jobs*, cada minuto:
```
* * * * * /usr/bin/php /home/u489236361/domains/nodico.com.mx/public_html/artisan schedule:run >> /home/u489236361/domains/nodico.com.mx/public_html/storage/logs/cron.log 2>&1
```
*Comprobación:* a los 30 minutos hay líneas en `storage/logs/tareas.log`
(`docs/DEPLOY.md`, «Verificar que corre de verdad»), y ninguna es de
`nodico:demo-al-dia`: esa tarea solo se ejecuta en `staging` y `local` (aunque
`schedule:list` la enseñe, porque lista sin aplicar el filtro de entorno).

**D11. Lo que ve el público.**

| Comprobación | Esperado |
|---|---|
| `curl -s https://nodico.com.mx/robots.txt` | Sin `Disallow: /` |
| Portada | Sin el distintivo «Ambiente de prueba» |
| `/aviso-de-privacidad`, `/terminos` | La versión revisada por jurídico (E6) |
| `/esto-no-existe` | Pantalla 404 de Nódico |
| Rutas internas (`/composer.lock`, `/storage/logs/laravel.log`, `/config/nodico.php`) | `403` o `404`; nunca `200` (el despliegue ya lo comprueba) |

**D12. Un registro de verdad.** Crear una cuenta con un correo propio:
*Comprobación:* llega el correo de verificación, se confirma, se aceptan los
textos legales y se entra al portal.

**D13. Un pago de verdad (E1).** Con esa cuenta, un Day-Pass con una tarjeta
real.
*Comprobación:* la página del banco pide la tarjeta y el 3-D Secure, se vuelve a
Nódico con «¡Listo! Tu pago quedó confirmado» y la membresía queda activa.
Después, **devolver ese cargo desde el panel de BBVA**: Nódico no tiene proceso
de devoluciones (decisión pendiente, `docs/LEGAL-PENDIENTES.md`).

**D14. El torno (E8).** En la PC de la oficina, en el `.env` del agente:
`NODICO_URL=https://nodico.com.mx` y `NODICO_SECRETO` igual a
`ACCESO_AGENTE_SECRETO` del servidor; reiniciar el agente.
*Comprobación:* en el panel, *Accesos* muestra el agente conectado, y una
persona del equipo pasa por el torno y aparece su entrada.

**D15. Formulario de contacto.**
*Comprobación:* un mensaje desde la portada llega a `contacto@nodico.com.mx`.

**D16. Avisar.** A recepción y administración: dirección nueva, cuentas
nuevas (las de demo **no existen** en producción).

### Si algo sale mal

- **Antes de D9** nadie ve nada: se corrige y se repite el paso.
- **Después de D9**: `ssh … "cd R && php artisan down"` deja una página de
  mantenimiento mientras se arregla. Para retirar el sitio del todo, se borra el
  registro `A` de la raíz (antes no existía; Odoo en `www` no se toca).
- Nunca se copia la base de pruebas a producción ni al revés.

---

## 4. Raíz del documento: las dos formas de montarlo

**Recomendada (E4):** la raíz de `nodico.com.mx` apunta a `public_html/public`.
La aplicación queda fuera del alcance de HTTP por diseño. Requiere hacer el
cambio en hPanel y ensayarlo antes en pruebas (P4). El script de despliegue
está escrito para el montaje plano de pruebas; con la raíz en `public/`, los
estáticos que sube a la raíz (`img`, `fonts`, `icons`, sueltos de `public/`)
tienen que ir a `public/`. **Ese ajuste se hace y se prueba en P4, en pruebas,
no el día del lanzamiento.**

**Alternativa probada:** el mismo montaje plano de `prueba.nodico.com.mx`, con
`index.php` propio en la raíz y las carpetas de aplicación cerradas por
`.htaccess`. El despliegue comprueba en cada corrida que nada interno responda
`200` y falla si algo lo hace. Es lo que está en uso hoy.

---

## 5. Lo que ya está asegurado en el código

- **Los datos demo no tocan producción.** `nodico:demo-al-dia`, `DemoSeeder` y
  `DatabaseSeeder` se niegan a correr fuera de `local`, `staging` y `testing`:
  lista blanca, para que un `APP_ENV` mal escrito (`prod`, `produccion`)
  tampoco baste. La tarea programada solo existe en `staging` y `local`.
  Pruebas: `DemoAlDiaTest::test_no_corre_en_produccion`,
  `test_un_entorno_desconocido_tambien_se_niega`, `DemoSeederTest`,
  `DatabaseSeederTest`.
- **`deploy.sh` sabe dónde está.** Recibe la raíz de `deploy_prueba.py`, siembra
  el contenido público solo en pruebas o en el primer despliegue (en producción
  no pisa los precios que cambie el administrador) y no ejecuta nada de demo
  fuera de pruebas. Las comprobaciones por HTTP del final van contra el dominio
  desplegado, no contra pruebas.
- **Stripe** queda instalado y sin llaves: no se activa solo.
- **Google** queda apagado: sin las tres credenciales el botón no aparece aunque
  se encienda el interruptor.
