# Smart Pass — contrato de integración (Fase 0)

Descubrimiento de la API y el esquema de **Smart Pass System** (terminal HFSecurity
FR07), para que Nódico se vuelva la única interfaz sin hablarle nunca al FR07.

> **Método:** todo lo de este documento se obtuvo **sin disparar acciones con
> efecto físico ni crear datos**: leyendo el esquema de MySQL (solo lectura) y el
> código de la SPA (`ROOT/MIPS/js/app.*.js`) y la config del backend. Los cuerpos
> exactos de cada petición y los mensajes de error de foto se confirman **en vivo
> en el navegador contigo**, antes de la Fase 3 — no los disparé a ciegas porque
> crean personas o abren la puerta de verdad.

## Entorno (verificado en la computadora de la oficina)

| Dato | Valor |
|---|---|
| App | Spring Boot `com.tendcent.face.owl`, Tomcat 8.5, perfil **prod** |
| Interfaz y API | `http://localhost:9000` (context-path `/`), HTTPS en 9443 |
| Sesión | Cookie `http-only`, expira a 10 días |
| MySQL | `localhost:3307`, base `tdx_face_owl` (MySQL 5.7) |
| Canal del terminal | RPC propietario en 7788 — **no se toca** |
| App en disco | `C:\SMART_PASS\tomcat_v4\webapps\ROOT` (SPA Vue + Element-UI en `/MIPS`) |

**Credenciales:** viven en `C:\SMART_PASS\tomcat_v4\webapps\ROOT\WEB-INF\classes\application-prod.properties`
(MySQL, usuario root) y en los `.bat` de `C:\SMART_PASS`. **No se transcriben aquí
ni se versionan**: van al `.env` del agente. La de MySQL hay que rotarla (ver Seguridad).

---

## Las dos preguntas que deciden el plan

- **¿Apertura remota de la puerta?** → **SÍ.** Existe `POST …/devices/remote/opendoor`
  (junto a `/reset`, `/restart`, `/set_time`). **La Fase 4 es viable por API.**
- **¿Enrolar rostro mandando una foto por API?** → **SÍ.** El alta de empleado
  (`/admin/person/employees`) sube foto (`photo`/`base64`/`photoUpload` → `resourceId`,
  con `FormData`), `spring.servlet.multipart.max-file-size=-1` (sin límite), y existe
  importación masiva con fotos (`/import`, `person_excel`). **La Fase 3 es viable por
  el camino preferente** (capturar en el navegador y enviar). El formato exacto
  (base64 vs multipart) y el mensaje de rechazo de foto se confirman en vivo.

---

## 1 · Autenticación

- **`POST /admin/login`** — campos `username`, `password` (puede pedir `captcha`;
  la SPA muestra `captchaSrc`). La respuesta establece la **cookie de sesión** que
  sostiene todo lo demás; el agente la guarda y la reusa hasta que caduque, y
  re-loguea al recibir 401.
- La SPA firma sus peticiones (`sign`/`signature`, con `crypto-js`). **Por confirmar
  en vivo:** si la firma es obligatoria en el servidor o solo del lado del cliente,
  y si `password` viaja en claro, hasheada o firmada. Es lo primero a capturar en
  el navegador (es una lectura, sin efectos).

## 2 · Eventos de acceso — se leen de la BD, no de la API

Tabla **`tdx_pass_record`** (297 filas hoy). El agente lee por id incremental:
`SELECT … FROM tdx_pass_record WHERE id > :ultimo AND deleted_flag = 0 ORDER BY id ASC`.

| Columna | Significado |
|---|---|
| `id` (PK, int) | **Identificador incremental.** Es el cursor y la clave de idempotencia. |
| `create_time` (datetime) | **Hora del evento** (la que se usa, no la de recepción). |
| `person_id` (int) | Persona reconocida → `tdx_person.id`. **`-1` = extraño.** |
| `person_type` (smallint) | **`1` = miembro reconocido · `-1` = «extraño»** (el campo que los distingue). |
| `pass_type` (varchar) | Modo: `face_2` (368), `face_0` (40). Reconocimiento facial. |
| `sub_pass_type` | Subtipo (hoy null). |
| `direction` (smallint) | `1`/`2` = entrada/salida de reconocidos; `3` = evento de extraño. |
| `device_id` / `device_key` | Terminal de origen. |
| `img_uri` | Foto capturada del evento (dato biométrico — no se copia a Nódico). |
| `temperature`, `mask_state`, … | Extras del terminal. |
| `deleted_flag` | Borrado lógico; se ignora `= 1`. |

**Regla derivada (Fase 2):** `person_type = -1` (o `person_id = -1`) es un **extraño**
→ se guarda como tal, **nunca genera check-in ni consume día**. `person_type = 1`
con `person_id` válido → check-in del miembro amarrado a ese `person_id`.

Conteo actual coherente con lo esperado: **372 eventos `person_type = -1`** (extraños)
y 36 de miembros. (La API equivalente, por si se necesita, es
`GET /admin/pass/pass_records/page`; pero la fuente de verdad es la BD.)

## 3 · Personas y el amarre con Nódico

| Tabla | Rol |
|---|---|
| `tdx_person` | Identidad base: `id` (PK), `person_no`, `type`, `name`, `id_card`, `expire_time`. |
| `tdx_employee` | Ficha de empleado/miembro: `id`, **`person_id` → `tdx_person.id`**, `phone`, `email`, `group_id`. |
| `tdx_person_photo` | Rostro: `id`, `person_id`, `resource_id` (la imagen), `status`. |
| `tdx_person_finger`, `tdx_person_palm` | Huella/palma (sin uso: 0/6 filas). |
| `tdx_blacklist` | Lista negra. |
| `tdx_device_base_info` (+ `_config`, `_group`, `_log`) | Terminales. |

**El amarre** (Fase 2): Nódico guarda en el usuario su **`person_id` de Smart Pass**
(`tdx_person.id`). Sin él, `tdx_pass_record.person_id = 147` no significa nada.

## 4 · Alta de persona y carga de rostro (Fase 3)

- **`POST /admin/person/employees`** — crea la persona. Campos vistos en la SPA:
  `personNo`, `personName`, `gender`, `phone`, `email`, `group_id`, y la **foto**
  (`photo`/`base64`/`photoUpload`, subida con `FormData`; hay `base64ToFile`, así que
  acepta base64 del navegador). Devuelve el id de persona → se guarda en el miembro.
- **Importación:** `/admin/person/employees/import`, `/person_excel`,
  `/download_from_device` (bajar del terminal).
- **Por confirmar en vivo (con una alta de prueba que luego se borra):** el nombre
  exacto del campo de la foto y el **JSON de error cuando la foto no sirve** (cara de
  perfil, varias caras, mala luz) — ese texto es el que Nódico traducirá a
  instrucciones claras.

## 5 · Apertura remota (Fase 4)

- **`POST …/devices/remote/opendoor`** — abre la puerta. Parámetros (device_key/id) y
  qué confirma el éxito: **por capturar en vivo** (esta sí tiene efecto físico, así
  que se prueba contigo presente y con intención). Existen también
  `/devices/remote/reset`, `/restart`, `/set_time`.

## 6 · Baja / desactivación de persona (Fase 2/3)

- **`DELETE /admin/person/employees`** (baja) y `/admin/person/blacklists` (lista negra).
  Cuerpo exacto por confirmar. Para cuando alguien deja de ser miembro.

---

## Rutas de API observadas (referencia)

`/admin/login` · `/admin/pass/pass_records[/page|/export]` ·
`/admin/attendance/record/*` · `/admin/person/employees[/import|/permission|/download_from_device]` ·
`/admin/person/blacklists` · `/admin/devices[/config|/remote/restart]` ·
`/devices/remote/opendoor|reset|restart|set_time` · `/access_auth`, `/access/rule` (reglas de acceso).

## Condiciones para cerrar la Fase 2 (Diego, 2026-08-31)

1. **Contradicción 297 vs 372 + ¿purga?** — **Resuelto.** El «297» (y luego «301») es la
   *estimación imprecisa de InnoDB* de `information_schema.table_rows`, no un conteo. El
   `COUNT(*)` real es **408** = 372 extraños (`person_type=-1`) + 36 miembros (`=1`).
   Rango de id 1–438 con **0 borrados lógicos** → ~30 ids ausentes por gaps de
   auto_increment (rollbacks) o borrados manuales puntuales. **No hay purga automática:**
   sin eventos programados en MySQL, sin política de retención en la config, sin tareas de
   borrado. *Implicación de diseño:* el agente **no asume continuidad de ids** (puede haber
   huecos), usa el id de origen como clave de idempotencia **y** como cursor, y **Nódico
   conserva todo lo recibido** aunque Smart Pass llegara a borrarlo.

2. **Zona horaria** — `create_time` es un `datetime` **naive en hora local de Mérida**
   (`America/Merida`, UTC−6, sin horario de verano). Laravel/Nódico opera en **UTC**
   (`config/app.php` → `'UTC'`). **Regla:** el agente interpreta `create_time` como
   `America/Merida` y lo envía en **ISO 8601 con offset `-06:00`** (o ya convertido a UTC);
   Laravel almacena en UTC y el portal lo muestra de nuevo en Mérida. **Nunca** se envía una
   fecha naive: sería la causa clásica de eventos con 6 horas de corrimiento.

3. **Antirrebote (reconocimientos repetidos)** — el terminal puede emitir varios eventos de
   la misma cara en pocos segundos (la persona parada frente al lector). Regla, configurable,
   **default 90 s**: un reconocimiento (`person_type=1`) **siempre se guarda** como evento de
   acceso (auditoría), pero **no genera un check-in nuevo** si el mismo `person_id` ya tuvo un
   reconocimiento en la misma `direction` dentro de la ventana. Es independiente del
   incremento de `dias_usados` (que ocurre una sola vez por día natural). Valor ajustable en
   la config de Nódico; queda como decisión propuesta, revisable.

4. **Id que retrocede** — el agente guarda el último id procesado (cursor). Si en una lectura
   el `MAX(id)` de Smart Pass es **menor** que el cursor, el auto_increment se reinició
   (reinstalación o reset de `tdx_face_owl`). El agente **no avanza a ciegas**: registra el
   incidente, lo refleja en su endpoint de salud, **avisa a Laravel** y **se detiene** hasta
   intervención humana (no re-procesa desde 1 ni salta eventos).

## Estado del apagado de Druid (condición «ahora»)

- **Config cambiada en disco** (`application.properties`): `stat-view-servlet.enabled=false`
  y `allow=127.0.0.1`. Backup guardado junto al archivo.
- **Pendiente de aplicar:** requiere **reiniciar Tomcat**, que hoy corre como proceso
  **elevado (administrador)**; el shell del agente de Claude no tiene ese privilegio, así que
  no pudo reiniciarlo. Se aplica al reiniciar Smart Pass como administrador, o al dejar
  Tomcat como servicio de Windows en la Fase 1 (que arrancará con la config nueva).

## Hallazgos de seguridad (para la Fase 6)

1. **Consola Druid expuesta:** `spring.datasource.druid.stat-view-servlet.enabled=true`
   en `/druid/*`, con `allow=` **vacío** (sin restricción de IP) y login por defecto
   `system` / `123456`. **Desactivar** (o al menos restringir a `127.0.0.1` y cambiar
   la clave).
2. **MySQL `root` con contraseña en texto plano** en `application-prod.properties` y en
   los `.bat`. **Rotar** la contraseña de root y sacarla de los `.bat`; el agente usa
   su propia credencial desde su `.env`.
3. El envío de rastreos de error del fabricante, si está activo, se desactiva.

## Lo que queda por confirmar EN VIVO (contigo, sin disparar a ciegas)

Son capturas de red en el navegador; las de lectura las hago yo, las de efecto físico
o que crean datos las hacemos juntos y se limpian después:

1. **Login**: cuerpo exacto y si la firma es obligatoria (lectura).
2. **Alta con foto**: campo de la foto y JSON de error de foto inválida (alta de prueba → borrar).
3. **opendoor**: parámetros y confirmación de éxito (tiene efecto físico → contigo).
4. **Baja**: cuerpo del DELETE.

Con esto validado, la Fase 1 (el agente) puede escribirse sobre un contrato firme.

---

# Reconexión del terminal FR07 (Fase 0 — feature/reconexion-terminal)

El FR07 pierde la sesión con Smart Pass y **no la reestablece solo**: queda «Sin
conexión» hasta que una persona entra a la plataforma, va a *Dispositivo → Detalle →
Red*, reescribe **la misma** contraseña de comunicación LAN y pulsa **Grabar**. Ese
guardado no corrige nada: envía una orden que **obliga al equipo a re-registrarse**.

**Causa de fondo (física):** el enlace wifi pierde ~12 % de los paquetes (7–388 ms,
cortes de segundos). Una conexión TCP permanente no sobrevive. La solución real es
**cable de red + IP fija**. Lo de abajo es una red de seguridad, no el arreglo.

## A · Detección de estado — LECTURA, sin tocar el equipo

El agente corre en la **misma máquina** que el MySQL de Smart Pass, así que la lectura
más barata NO es una llamada HTTP sino la BD local (cero impacto, cero desgaste):

```sql
SELECT is_online, last_active_time
FROM tdx_device_base_info
WHERE device_key = :device_key;   -- FR07 «Nodico» = AA312DF45B59A915
```

- `is_online` = 1 en línea / 0 sin conexión.
- `last_active_time` = último latido del equipo.

Alternativa por API (si algún día el agente no comparte máquina con la BD):
`POST /admin/devices/is_online` (función SPA `checkDeviceWhetherIsOnline`). La BD es
preferible: es local y no depende de la sesión de admin.

> Regla: **se lee cada 30 s; se considera caído solo tras DOS lecturas seguidas en 0**
> (un minuto). Una sola lectura en 0 puede ser un paquete perdido, no una caída.

## B · Reconexión — dos candidatas (a confirmar EN VIVO, contigo)

Ambas salen del JS de la SPA (`app.b8b3fe4e.js`). Base de rutas: `o = "/admin"`.

| # | Qué hace | Ruta · método | Cuerpo (según SPA) | Coste |
|---|----------|---------------|--------------------|-------|
| 1 | **Reiniciar** (Control remoto) | `PUT /admin/devices/remote/restart` | `[deviceId]` — array crudo, ej. `[1]` (SPA: `fetchDeviceRestart([e.id])`) | Reinicio del equipo (~30–60 s fuera). **No** escribe flash. |
| 2 | **Grabar contraseña LAN** (lo que funciona hoy) | `PUT /admin/devices/network/set_password` | `{...}` **por confirmar** (SPA: `fetchDevicesPassword(e)`) | Escribe configuración a **flash** → fuerza re-registro. |

**Recomendación (responde al «prefiere la llamada más barata» del encargo):**
probar primero **`remote/restart`** — no desgasta flash. PERO hay una duda física que
solo la captura en vivo resuelve: *si la sesión ya está muerta, ¿el servidor logra
entregarle un comando remoto al equipo?* El truco de `set_password` funciona HOY con la
sesión caída, lo que sugiere que llega por otra vía. Por eso:

- En la sesión en vivo probamos `remote/restart` con el equipo caído. Si lo revive →
  esa es la buena (más barata). Si no llega → usamos `set_password` como el mecanismo
  real y `restart` solo como intento previo.

## C · Familia de Control remoto observada (referencia)

```
PUT /admin/devices/remote/restart     reiniciar
PUT /admin/devices/remote/reset       restablecer   (NO usar: borra config)
PUT /admin/devices/remote/opendoor    abrir puerta  (ya en uso)
PUT /admin/devices/remote/set_time    ajustar hora
PUT /admin/devices/network/set_password   contraseña LAN («Grabar»)
PUT /admin/devices/network/set_network    red del equipo
GET /admin/devices/is_online              estado (POST en SPA)
```

## D · Lo que FALTA capturar EN VIVO (contigo, una sola vez, avisando antes)

Estas dos tienen efecto sobre el equipo:

1. **`set_password` («Grabar»)**: DevTools → pestaña Red → *Dispositivo → Detalle →
   Red* → reescribir la MISMA contraseña LAN (hoy de fábrica `12345678`) → **Grabar**.
   Anotar: cuerpo exacto (¿lleva `deviceKey`? ¿`id`? ¿`password` en claro o cifrada?),
   y **qué devuelve en éxito vs. en error** (para distinguir sin adivinar).
2. **`remote/restart`**: con el equipo caído, ejecutarlo y ver si lo revive y qué
   responde (código 200 y `code` interno).

La lectura de estado (A) ya está confirmada por BD; no necesita captura.

> **Contraseña LAN**: va SOLO en el `.env` del agente, nunca en el repo. Hoy es
> `12345678` (de fábrica). Pendiente: cambiarla **de forma coordinada** una vez que la
> reconexión funcione — cambiarla antes rompe el mecanismo.
