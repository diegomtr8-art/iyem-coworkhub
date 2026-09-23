# API móvil de Nódico — `/api/v1`

> **Estado:** implementado el 22/09/2026 (rutas en `routes/api-movil.php`, controladores en `app/Http/Controllers/Api/Movil/`,
> pruebas en `tests/Feature/ApiMovil/`). Las decisiones están en el §9 y lo que cambió al construirlo, en el §11.
>
> **Rama:** `feature/app-movil`, sacada de `feature/reconexion-terminal` (que contiene a `main` entero y 59 commits más).

---

## 1. Lo que hay hoy y lo que falta

| Pieza | Hoy | Para la app |
|---|---|---|
| Sanctum | En `composer.json` (4.x), **sin publicar**: no hay `config/sanctum.php`, ni tabla `personal_access_tokens`, ni `HasApiTokens` en `User`. | Publicar, migrar y añadir el trait. |
| Rutas API | Solo `api/acceso/*` (agente del torno, firma HMAC). | Nuevo archivo `routes/api-movil.php` bajo `/api/v1`, sin tocar `api/acceso`. |
| Reglas de reserva | **Viven dentro de `Portal\ReservasController`** (traslape, tope diario, saldo, cancelación). | Se extraen a un servicio que usan la web **y** la API. Ver §3. |
| Credencial de acceso | **No existe.** El acceso físico es por rostro en el FR07; no hay QR ni credencial digital. | Se crea: QR que recepción valida (§6.9). |
| Enlace mágico | Atado a la **sesión del navegador** que lo pidió. | Variante para la app, atada al **dispositivo** (§5.3). |
| Google | Flujo web de Socialite con `state` en sesión. | La app obtiene un `id_token` de Google y el servidor lo verifica (§5.2). |
| Middlewares de estado | `no.suspendida`, `consentimiento`, `verified` **redirigen** a pantallas web. | Versiones para API que responden JSON con un `codigo` (§4.3). |

---

## 2. Principios

1. **Versionada desde el primer día.** Todo bajo `/api/v1/`. Un cambio que rompa la forma de una respuesta abre `/api/v2`; la v1 sigue viva mientras haya teléfonos con la versión vieja.
2. **Dos caras, y nada más.** La app es el **portal del miembro** completo y, para administración, **solo los reportes**. Toda la operación (agenda, miembros, caja, check-ins, accesos, asesorías, catálogos, facturación) se queda en la web.
   - Miembro → token con habilidad `miembro`, todas las rutas del §6.1–6.10.
   - Cuenta del equipo **con el permiso `ver-reportes`** (hoy solo el rol `admin`) → token con habilidad `reportes`, que solo abre el §6.11. No puede tocar ninguna ruta de miembro ni escribir nada.
   - Staff y caja (sin `ver-reportes`) → `403 cuenta_operativa`: «Tu trabajo se hace desde el panel web».
3. **Recursos, nunca modelos.** Cada respuesta pasa por un `JsonResource` en `app/Http/Resources/Movil/`. Nada de `$modelo->toArray()`: si un campo no está escrito en el recurso, no sale.
4. **Todo es «lo mío».** No hay una sola ruta que reciba un `user_id`. El dueño siempre es `$request->user()`. Cuando una ruta recibe el id de un objeto (reserva, asesoría, orden), se comprueba que sea del usuario del token **y se responde `404`, no `403`**: un `403` confirma que ese id existe y es de otra persona.
5. **El servidor decide.** La app puede anticipar (la API le da los números para hacerlo: saldo, tope, lo ya usado ese día), pero las reglas se vuelven a aplicar al escribir. Mismo servicio para web y API: una regla, un sitio.
6. **Textos listos para mostrar.** Igual que el portal: los mensajes de error y los textos de estado («Se reinicia el 1 de octubre») los arma el servidor, en español. La app no traduce ni recompone reglas.

---

## 3. Refactor previo: una sola copia de las reglas

Hoy el portal web tiene la lógica de reservar metida en el controlador. Si la API la copiara, tendríamos dos motores que tarde o temprano discrepan. Por eso, **antes de escribir la API**:

| Se extrae de | A | Qué contiene |
|---|---|---|
| `Portal\ReservasController::store()` + `verificar*()` | `App\Servicios\Reservas\ServicioDeReservas::reservar()` | Vigencia, espacio reservable, bolsa incluida, calendario (`ValidadorDeReserva`), traslape con bloqueo, tope diario, saldo, índice único, asiento en el libro. |
| `Portal\ReservasController::destroy()` | `ServicioDeReservas::cancelar()` | Recarga con bloqueo, regla de las 2 h, devolución en el libro, liberar bloques. Devuelve si devolvió horas. |
| `Portal\ReservasController::paraLaVista()` | `ServicioDeReservas::` / recurso | `cancelar_devuelve`, `limite_cancelacion`. |
| `SuscripcionController::asignarAcompanante()` / `quitarAcompanante()` | `App\Servicios\Membresias\GestorDeAcompanante` | Reglas del Match (correo verificado, no suspendida, no uno mismo, no dos Match). |
| `SuscripcionController::planParaLaVista()` | `App\Servicios\Membresias\DescripcionDelPlan` | Las frases de «qué incluye». |
| `ResuelveLaMembresia` (trait) | `App\Servicios\Membresias\MembresiaDelMiembro` | Membresía vigente y el bloque de estado. El trait queda como envoltorio del servicio. **Cambia la regla:** la vigente es la propia o, si no hay, la Match de la que la persona es acompañante (`companion_user_id`), con su rol (`titular` / `acompanante`). |
| `ReservasController::verificarTopeDiario()` y `::horasDeEseDia()` | `ServicioDeReservas` | **Tope diario por persona** en las dos. Hoy se contradicen: al reservar se cuenta por persona y la disponibilidad lo cuenta por membresía, así que la pantalla podía decir «ya no te queda» cuando sí quedaba. |
| `GestorDeAsesorias::solicitar()` y `::verificarCupo()` | (se corrige en su sitio) | La solicitud se guarda con el `user_id` de **quien la pide** (hoy siempre el del titular) y el tope diario se cuenta por persona. |
| `CheckoutController` / `OrdenPagoController::generar()` | `CobroConTarjeta` / `GeneradorDeReferencia` | Ver §6.7. |

Los servicios lanzan `ValidationException` con los mismos textos de hoy. El controlador web los convierte en redirección con errores (como ahora) y el de API en `422` JSON. **Las pruebas actuales del portal tienen que seguir en verde sin tocarlas**: son la prueba de que el refactor no cambió ninguna regla.

`ResumenDeBolsas`, `LibroDeHoras`, `Disponibilidad`, `ValidadorDeReserva` y `GestorDeAsesorias` ya son servicios y se usan tal cual.

---

## 4. Convenciones

### 4.1 Petición

- `Authorization: Bearer <token>` en todo lo autenticado.
- `Accept: application/json` siempre.
- `X-App-Version: 1.0.0` y `X-App-Plataforma: ios|android` en todas las peticiones (para soporte y para forzar actualización).
- `Idempotency-Key: <uuid>` **obligatorio** en `POST /reservas`, `POST /asesorias`, `POST /pagos/tarjeta` y `POST /pagos/referencia`. En móvil la red se corta a media petición y la app reintenta: sin esta cabecera, un reintento es una segunda reserva. El servidor guarda la respuesta 24 h por usuario + clave y devuelve la misma si llega repetida.

### 4.2 Respuesta

- Éxito: `{ "data": ... }`. Las listas paginadas añaden `meta.siguiente` (cursor) o `null`.
- Fechas de calendario: `"2026-09-22"`. Horas: `"09:00"`. Instantes: ISO 8601 con zona, `"2026-09-22T09:00:00-06:00"` (zona `America/Merida`).
- Dinero: número con dos decimales en MXN (`599.00`).
- Horas de bolsa: número decimal (`1.5`); `null` significa ilimitada o no aplica, según el campo (se documenta en cada uno).

### 4.3 Errores

Todos llevan `message` (listo para mostrar) y, cuando la app tiene que **hacer algo distinto**, un `codigo` estable:

| HTTP | `codigo` | Qué hace la app |
|---|---|---|
| 401 | `no_autenticado` | Token ausente, caducado o revocado → borrar token y volver al acceso. |
| 403 | `cuenta_suspendida` | Pantalla de cuenta suspendida, con `detalle` (motivo) y contacto. |
| 403 | `correo_sin_verificar` | Pantalla «verifica tu correo» con botón de reenviar. |
| 403 | `consentimiento_pendiente` | Pantalla de aviso de privacidad / términos; trae `documentos` pendientes. |
| 403 | `cuenta_operativa` | Cuenta del equipo sin permiso de reportes: «Tu trabajo se hace desde el panel web.» |
| 403 | `sin_permiso` | Token de una cara pidiendo una ruta de la otra (reportes ↔ miembro). |
| 403 | `sin_membresia` | La acción exige membresía vigente (reservar, asesoría). |
| 403 | `solo_titular` | El acompañante del Match intentó algo que solo hace el titular (renovar, cambiar de acompañante). |
| 403 | `plan_no_incluye` | La bolsa no está en el plan; trae `sugerencia` con el plan que la incluye. |
| 422 | `datos_fiscales_incompletos` | Pidió factura sin datos fiscales completos → llevar a Datos fiscales. |
| 404 | — | No existe **o no es tuyo** (indistinguible a propósito). |
| 409 | `version_obsoleta` | `X-App-Version` por debajo de la mínima → pantalla «actualiza la app». |
| 422 | — | Validación. Formato estándar de Laravel: `errors.campo[]`, con los mismos textos del portal (p. ej. *«No te alcanza: esta reserva son 2 h y te quedan 1.5 h de Salas en este ciclo.»*). |
| 429 | `demasiadas_peticiones` | Trae `reintentar_en` (segundos). |

El orden de las comprobaciones de estado es fijo: token → versión → rol → suspendida → correo verificado → consentimiento. Así la app ve siempre el primer problema que tiene que resolver, no uno cualquiera.

### 4.4 Límites

| Grupo | Límite |
|---|---|
| Acceso (`/auth/*` sin token) | Los que ya existen en la web: `acceso` (60/min por IP) + el retraso creciente por cuenta de `ControlDeIntentos`; `recuperacion` para enlace mágico. |
| Lectura autenticada | 120 peticiones/min **por token**. |
| Escritura autenticada | 30/min por token; `POST /reservas` 10/min. |
| Descargas de factura | 20/min por token. |

### 4.5 Tokens

- Uno por dispositivo, con nombre legible (`"iPhone de Diego · iOS 19"`) que es lo que se ve en «Mi seguridad».
- Una sola habilidad por token: `miembro` o `reportes`, decidida por el servidor al emitirlo según el rol y los permisos (la app no la pide). Si a un admin le quitan `ver-reportes`, su token deja de servir en la siguiente petición: el permiso se vuelve a comprobar siempre, no solo al emitir.
- Los tokens de `reportes` caducan a los **15 días sin uso** y exigen segundo factor si la política de la web lo exige para ese rol: los reportes traen ingresos y datos de contacto de miembros.
- **Los de miembro caducan a los 60 días sin uso** (Sanctum guarda `last_used_at`; se comprueba en `Sanctum::authenticateAccessTokensUsing`). Quien abre la app tres veces por semana no vuelve a iniciar sesión nunca; un teléfono olvidado en un cajón pierde el acceso solo.
- El token se guarda **hasheado** en el servidor (lo hace Sanctum) y en el teléfono solo en `expo-secure-store`.
- Iniciar sesión otra vez desde el mismo dispositivo (`dispositivo_id` igual) revoca el token anterior de ese dispositivo.

---

## 5. Acceso

Todas sin token. Todas devuelven lo mismo al entrar:

```json
{
  "data": {
    "token": "12|Qx9…",
    "caduca_por_inactividad_en_dias": 60,
    "usuario": { "...": "igual que GET /yo" }
  }
}
```

…o bien, si la cuenta tiene segundo factor activo y el dispositivo no es de confianza:

```json
{ "data": { "requiere_dos_factores": true, "desafio": "d_8Kf…", "caduca_en": "2026-09-22T09:10:00-06:00" } }
```

El `desafio` es un identificador de un solo uso que vive 5 minutos y **no da acceso a nada** por sí mismo. Mismo principio que la web: ningún método de acceso rodea el segundo factor.

| Método | Ruta | Cuerpo | Notas |
|---|---|---|---|
| Contraseña | `POST /auth/token` | `email`, `password`, `dispositivo` (nombre), `dispositivo_id` (uuid que la app genera una vez) | Reutiliza `LoginRequest::authenticate()`: mismo retraso creciente, mismo hash señuelo contra enumeración, misma bitácora. |
| Segundo factor | `POST /auth/dos-factores` | `desafio`, `codigo` (TOTP o de recuperación) | Mismo `DosFactores::verificarCodigo()`. |
| Google | `POST /auth/google` | `id_token`, `dispositivo`, `dispositivo_id` | §5.2 |
| Enlace mágico — pedir | `POST /auth/enlace-magico` | `email`, `verificador` (SHA-256 de un secreto que la app genera y guarda) | Respuesta idéntica exista o no la cuenta. Solo si `NODICO_ENLACE_MAGICO_ENABLED`. |
| Enlace mágico — canjear | `POST /auth/enlace-magico/canjear` | `token` (del enlace), `secreto`, `dispositivo`, `dispositivo_id` | §5.3 |
| Reenviar verificación | `POST /auth/verificacion/reenviar` | — (con token) | Límite `reenvio`. |
| Cerrar sesión | `POST /auth/salir` | — (con token) | Revoca **este** token y borra su registro de notificaciones push. |

### 5.1 Contraseña olvidada y registro

**No se construyen en la app.** Los dos abren la web en el navegador del sistema (`/forgot-password`, `/register`). Tienen flujos por correo con verificación que ya funcionan, y duplicarlos es duplicar su superficie de ataque.

> **Ojo con Apple (guía 5.1.1(v)):** una app que permite **crear** cuenta tiene que permitir **borrarla** desde la app. El ingreso con Google crea cuentas (`OAuthController::crearDesdeProveedor`), así que la app **sí** necesita `DELETE /yo` (§6.1) aunque el registro sea en la web.

### 5.2 Google

1. La app obtiene un `id_token` de Google con el client ID de iOS o de Android.
2. El servidor lo verifica contra las llaves públicas de Google: firma, `iss`, `exp`, y que `aud` esté entre los client IDs configurados (`GOOGLE_CLIENT_ID_IOS`, `GOOGLE_CLIENT_ID_ANDROID`, `GOOGLE_CLIENT_ID` web). **Sin la comprobación de `aud`, un token emitido para cualquier otra app del mundo serviría para entrar a Nódico.**
3. Exige `email_verified = true`.
4. A partir de ahí, **la misma lógica** que la web: `resolverUsuario()`, `vincular()` y `crearDesdeProveedor()` se sacan de `OAuthController` a un servicio (`App\Servicios\Acceso\IdentidadDeProveedor`) que usan los dos.

Solo si `NODICO_GOOGLE_LOGIN_ENABLED`. **Por comprobar en la Fase 5:** si el ingreso con Google funciona en Expo Go o exige compilación de desarrollo.

### 5.3 Enlace mágico en el teléfono

En la web el enlace se ata al navegador con un secreto en la sesión. En la app no hay sesión, así que se ata al dispositivo con el mismo principio que PKCE:

1. La app genera un `secreto` aleatorio, lo guarda en `expo-secure-store` y manda solo su `verificador` (SHA-256).
2. El correo lleva un enlace a `https://<dominio>/app/enlace/{token}`, que abre la app (esquema `nodico://`; en desarrollo, la URL de Expo Go configurada en `NODICO_APP_URL_ENLACE`).
3. La app canjea `token + secreto`. El servidor comprueba `sha256(secreto) == verificador`, un solo uso, 15 minutos.

Un enlace reenviado o abierto en otro teléfono no sirve: ese teléfono no tiene el secreto. Se añade la columna `verificador` a `enlaces_magicos`; el flujo web no cambia.

---

## 6. Rutas autenticadas

Prefijo `/api/v1`. Middleware del grupo: `auth:sanctum`, `ability:miembro`, `movil.version`, `movil.miembro`, `movil.no-suspendida`, `movil.verificado`, `movil.consentimiento`, `throttle:movil`.

**Permiso** en cada tabla: *token* = basta un token válido de miembro al corriente; *membresía* = además hace falta membresía vigente; *plan* = además el plan tiene que incluir esa bolsa. Las rutas marcadas ⚑ se saltan el middleware de consentimiento (si no, no habría forma de aceptarlo).

### 6.1 Yo

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/yo` | token | `id`, `nombre`, `email`, `correo_verificado`, `avatar_url`, `telefono`, `empresa`, `ocupacion`, `face_id_ok`, `dos_factores_activo`, `tiene_contrasena`, `cara` (`miembro` o `reportes`), `contacto_emergencia{nombre, telefono, parentesco}`, `preferencias{reservas, membresia, comunidad}`. Responde también con correo sin verificar o consentimiento pendiente (la app lo necesita para esas pantallas). |
| PATCH | `/yo` | token | Mismos campos editables y validaciones que `PerfilController::update` (incluida la regla del contacto de emergencia sin teléfono). |
| POST | `/yo/foto` | token | `multipart/form-data`, campo `foto`: jpg/png/webp, 4 MB. Devuelve `avatar_url`. |
| DELETE | `/yo/foto` | token | — |
| GET | `/yo/datos-fiscales` | token | `datos{rfc, razon_social, regimen_fiscal, uso_cfdi, codigo_postal, email_facturacion}`, `completos`, catálogos SAT, `proceso{emisor, dias_habiles, contacto}`. **Registra la consulta en la bitácora**, igual que la web. |
| PUT | `/yo/datos-fiscales` | token | `GuardarDatosFiscalesRequest` tal cual. |
| GET | `/yo/dispositivos` | token | Tokens abiertos: `id`, `nombre`, `plataforma`, `ultimo_uso`, `creado`, `es_este`. |
| DELETE | `/yo/dispositivos/{id}` | token | Revoca ese token (cerrar sesión de un teléfono perdido desde otro). |
| GET ⚑ | `/yo/consentimiento` | token | `pendientes[]{documento, version, titulo, url}` |
| POST ⚑ | `/yo/consentimiento` | token | `documentos[]` aceptados; mismo registro que `ConsentimientoController::guardar`. |
| DELETE | `/yo` | token | Borrar cuenta. Exige `password` (o, sin contraseña, un `desafio` reciente de Google). Misma lógica que `ProfileController::destroy`. |

La contraseña, el segundo factor, el cambio de correo y las cuentas vinculadas **no se exponen**: la sección de seguridad del perfil abre `/seguridad` en el navegador. Son flujos con `password.confirm` y correos firmados que ya funcionan, y una segunda copia sería la que se queda sin actualizar.

### 6.2 Inicio

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/inicio` | token | Todo lo de la pantalla de Inicio en una sola llamada (en móvil, cada viaje de red cuesta): |

```json
{
  "estado_membresia": { "tiene": true, "tono": "atencion", "titulo": "Tu membresía termina en 5 días", "detalle": "Vence el 27 de septiembre.", "accion": "Renovar", "accion_url": "https://…/portal/contratar/3" },
  "membresia": { "id": 41, "plan": { "id": 3, "nombre": "Nodo Pro", "color": "#…" }, "fecha_fin": "2026-09-27", "dias_restantes": 5, "ciclo_inicio": "2026-09-01", "ciclo_fin": "2026-09-30" },
  "bolsas": [
    { "bolsa": "sala", "etiqueta": "Salas", "unidad": "h", "incluida": true, "ilimitada": false,
      "cupo": 10, "usado": 6.5, "restante": 3.5, "porcentaje_usado": 65,
      "tope_diario": 2, "reinicia_el": "2026-10-01T00:00:00-06:00", "reinicia_texto": "Se reinicia el 1 de octubre",
      "casi_agotada": false, "agotada": false },
    { "bolsa": "contenido", "incluida": false, "sugerencia": { "plan_id": 3, "plan": "Nodo Pro", "precio": 599.0, "incluye": 10 } }
  ],
  "proxima_reserva": { "id": 812, "espacio": "Sala de juntas 1", "fecha": "2026-09-23", "inicio": "10:00", "fin": "12:00", "empieza_en": "2026-09-23T10:00:00-06:00", "horas": 2 },
  "asesorias_pendientes": 1,
  "avisos": [ { "id": 5, "titulo": "…", "contenido": "…", "tipo": "info", "desde": "2026-09-20" } ],
  "face_id_pendiente": false
}
```

`bolsas` es exactamente `ResumenDeBolsas::para()` — la misma fuente que los medidores del portal. `accion` lleva a la pantalla de contratar de la propia app (§6.7); `accion_url` queda como respaldo web.

### 6.3 Espacios y disponibilidad

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/espacios` | membresía | Solo los reservables **cuya bolsa incluye tu plan** (mismo filtro que el portal): `id`, `nombre`, `tipo`, `tipo_etiqueta`, `capacidad`, `descripcion`, `imagen_url`, `amenidades[]`, `bolsa`, `bolsa_etiqueta`. Más `horizonte{desde, hasta}` y `operacion{granularidad_minutos, horas_para_cancelar_sin_penalizacion}`. |
| GET | `/espacios/{id}/disponibilidad?desde=&hasta=` | plan | Resumen por día para el calendario horizontal: `[{fecha, abierto, libres, total, motivo_cierre}]` (`Disponibilidad::delPeriodo`, recortado al horizonte). Máximo 45 días por llamada. |
| GET | `/espacios/{id}/disponibilidad/{fecha}` | plan | La franja del día (`Disponibilidad::delDia`: `apertura`, `cierre`, `bloques[{inicio, fin, libre, motivo}]`) **más** lo que hace posible la validación en vivo: `bolsa`, `saldo_ciclo`, `tope_diario`, `usado_ese_dia`. |

### 6.4 Reservas

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/reservas?tipo=proximas` | token | Confirmadas desde hoy, ascendente. |
| GET | `/reservas?tipo=pasadas&cursor=` | token | Pasadas, canceladas, completadas y no-show; descendente, 20 por página. |
| GET | `/reservas/{id}` | token | Una reserva (404 si no es tuya). |
| POST | `/reservas` | plan | Cuerpo: `espacio_id`, `fecha`, `hora_inicio`, `hora_fin`. Cabecera `Idempotency-Key`. `201` con la reserva **y** `bolsa_despues{restante, usado}` para que la app pinte el anillo actualizado sin otra llamada. `422` con el texto exacto de la regla que falló. |
| POST | `/reservas/{id}/cancelar` | token | `200` con `devolvio_horas` (bool) y `message` (el mismo texto de la web). Si ya no estaba confirmada, `409`. |

Cada reserva:

```json
{
  "id": 812, "estatus": "Confirmada",
  "espacio": { "id": 4, "nombre": "Sala de juntas 1", "tipo": "sala_juntas" },
  "fecha": "2026-09-23", "hora_inicio": "10:00", "hora_fin": "12:00", "horas": 2,
  "bolsa": "sala", "bolsa_etiqueta": "Salas",
  "empieza_en": "2026-09-23T10:00:00-06:00", "termina_en": "2026-09-23T12:00:00-06:00",
  "cancelar_devuelve": true,
  "limite_cancelacion": "2026-09-23T08:00:00-06:00"
}
```

`cancelar_devuelve` y `limite_cancelacion` salen de `Reserva::cancelarDevuelveHoras()`. La app usa `limite_cancelacion` para cambiar el texto del botón en el momento exacto aunque la pantalla lleve rato abierta; al cancelar, manda la respuesta del servidor y no la suya.

Se usa `POST …/cancelar` y no `DELETE`: la reserva no se borra, cambia de estado y deja rastro en el libro de horas.

### 6.5 Membresía

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/membresia` | token | `estado` (mismo bloque que Inicio), `vigente{id, fecha_inicio, fecha_fin, dias_restantes, precio_pagado, ciclo_inicio, ciclo_fin, proximo_reinicio, plan{…, incluye[], beneficios[]}}`, `bolsas` (solo incluidas), `acompanante{admitido, usuario{id, nombre, email}, face_id_ok}`, `renovacion{cobro_en_linea, metodo_pago{marca, ultimos4}, tiene_recurrente, renovacion_activa, en_periodo_de_gracia}`, `historial[]`. |
| POST | `/membresia/acompanante` | membresía | `email`. Reglas del Match vía `GestorDeAcompanante`. |
| DELETE | `/membresia/acompanante` | membresía | — |
| POST | `/membresia/renovacion/cancelar` | membresía | Cancela la renovación en Stripe (servidor a servidor: no necesita el SDK de Stripe en la app). |
| POST | `/membresia/renovacion/reactivar` | membresía | Solo en periodo de gracia. |

#### El acompañante del Match (decidido el 22/09/2026)

- **Ve la membresía compartida** en solo lectura: plan, vigencia y bolsas. `GET /membresia` le responde con `rol_en_membresia: "acompanante"` y el `titular{nombre}`; sin `renovacion` ni gestión del acompañante.
- **Reserva y pide asesoría contra la bolsa compartida.** Lo que consume uno, deja de estar para el otro.
- **El tope diario es por persona.** En sala, cada uno hasta 2 h al día (entre los dos, hasta 4 h ese día), descontando de las mismas 20 h. En asesoría, 1 h al día cada uno, de las mismas 4 h al mes.
- **Solo toca lo suyo.** Ve y cancela únicamente sus propias reservas y solicitudes. No ve las del titular, ni renueva, ni cambia de plan, ni cambia de acompañante. Las rutas de §6.5 que escriben responden `403 solo_titular`.
- Todo esto aplica **igual en el portal web**: la regla vive en el servicio, no en la app.

Los cambios que esto exige en el servidor están en el §3.

### 6.6 Asesoría IYEM

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/asesorias` | token | `incluida`, `bolsa{cupo, usado, restante, tope_diario, reinicia_el}`, `plan_que_la_incluye` (si no la tienes), `oferta[]{categoria, etiqueta, temas[]{id, nombre, descripcion_corta, duracion_min, asesores[]{id, nombre, foto_url}}}`, `franjas[]`, `solicitudes[]`. |
| POST | `/asesorias` | plan | `tema_id`, `detalle?`, `asesor_preferido_id?`, `dia_preferido`, `horario_preferido`, `horas?`. `Idempotency-Key`. Va por `GestorDeAsesorias::solicitar()`. |
| POST | `/asesorias/{id}/cancelar` | token | `GestorDeAsesorias::cancelar()`. |

Cada solicitud: `id`, `tema`, `dia_preferido`, `horario_preferido`, `horas`, `estado`, `estado_etiqueta`, `tono`, `pendiente`, `final`, `asesor`, `fecha_confirmada`, `notas`, `solicitada_el`.

### 6.7 Pagos y facturas

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/pagos?cursor=` | token | Órdenes de pago del miembro (lo de «Mis pagos»): `id`, `referencia`, `plan`, `monto`, `metodo`, `metodo_etiqueta`, `estado_pago`, `estado_pago_etiqueta`, `estado_factura`, `estado_factura_etiqueta`, `pide_factura`, `vence_el`, `reportado`, `tiene_pdf`, `tiene_xml`, `creada`. |
| GET | `/pagos/{id}` | token | Una orden, con `datos_bancarios` si es transferencia (para volver a ver la referencia). |
| POST | `/pagos/{id}/ya-pague` | token | Solo marca `reportado_pagado_en`, igual que la web. |
| GET | `/cobros?cursor=` | token | Los cargos que registra el panel (tabla `facturas`: folio, concepto, total, estatus, método, fecha de pago). |
| GET | `/facturas` | token | Solo las emitidas con PDF: `id`, `referencia`, `plan`, `monto`, `folio_fiscal`, `emitida_en`, `tiene_xml`. |
| GET | `/facturas/{id}/pdf` · `/facturas/{id}/xml` | token | El archivo. La app lo baja con su token (`expo-file-system`) y lo abre con la hoja de compartir. |

#### Contratar y renovar desde la app (decidido el 22/09/2026)

El pago se hace **dentro de la app**. Las dos vías que ya tiene la web, con la misma regla de siempre: **la app nunca activa nada**; la membresía la activa el webhook de Stripe (tarjeta) o caja al confirmar (referencia).

| Método | Ruta | Permiso | Qué hace |
|---|---|---|---|
| GET | `/planes/contratables` | token | Planes públicos con su `DescripcionDelPlan`, marcando `es_el_actual`, más `metodos{tarjeta, referencia}` disponibles y `tiene_datos_fiscales`. |
| POST | `/pagos/tarjeta` | token | `plan_id`. Prepara el cobro para la hoja de pago nativa de Stripe (PaymentSheet): devuelve `modo` (`suscripcion` o `pago_unico`), `tipo_intent` (`setup` o `payment`), `client_secret`, `llave_publica`, `nombre_comercio`, `plan`. Sin llave efímera: la hoja no guarda tarjetas para reutilizar. `Idempotency-Key`. |
| POST | `/pagos/tarjeta/suscribir` | token | Solo plan recurrente, después de la hoja en modo `setup`: `plan_id`, `setup_intent_id`. El servidor comprueba en Stripe que el SetupIntent es **de este cliente** y crea la suscripción. Devuelve `requiere_accion` y, si el banco pide 3-D Secure, el `client_secret` para `handleNextAction`. `Idempotency-Key`. |
| GET | `/pagos/tarjeta/estado` | token | `{ activa: bool }`. La app lo consulta tras cerrar la hoja de pago hasta que el webhook confirme, como la pantalla «confirmando» de la web. |
| POST | `/pagos/referencia` | token | `plan_id`, `metodo` (transferencia/efectivo), `pide_factura`. Crea la orden y devuelve la referencia con sus instrucciones (y datos bancarios si es transferencia). Si pide factura sin datos fiscales completos: `422` con `codigo: datos_fiscales_incompletos`. `Idempotency-Key`. |

- **Tarjeta:** los datos de la tarjeta van directos del teléfono a Stripe; nunca pasan por Nódico, igual que con Elements en la web. Plan recurrente: la suscripción se crea en el servidor con el primer cobro pendiente y la hoja de pago lo confirma (resuelve 3-D Secure sola). Plan de pago único: el mismo `PaymentIntent` con metadata que usa la web.
- La lógica de `CheckoutController` y de `OrdenPagoController::generar()` se saca a servicios (`App\Servicios\Pagos\CobroConTarjeta`, `App\Servicios\Pagos\GeneradorDeReferencia`) que usan la web y la API. Se suma al §3.
- Solo el titular contrata o renueva la membresía que tiene. El acompañante de un Match puede contratar un plan **propio** si quiere.
- **Por comprobar antes de construirlo:** si la librería nativa de Stripe funciona en Expo Go o necesita compilación de desarrollo. Si la necesita, en Expo Go el botón de tarjeta queda oculto (`/estado` lo indica) y se prueba ahí la referencia, que es pura API.
- **Apple:** la membresía da acceso a un espacio físico, así que se puede cobrar con Stripe sin las compras dentro de la app de Apple. Se confirma contra las directrices vigentes en la Fase 7 y se deja escrito.

### 6.8 Avisos

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/avisos` | token | `anuncios[]` (del coworking, vigentes) y `comunicados[]` (los personales, con `leido`). |
| POST | `/avisos/comunicados/{id}/leido` | token | Marca un comunicado propio como leído. |

### 6.9 Credencial

| Método | Ruta | Permiso | Respuesta `data` |
|---|---|---|---|
| GET | `/credencial` | token | `nombre`, `avatar_url`, `plan`, `color_plan`, `vigente_hasta`, `face_id_ok`, `codigo` (lo que va en el QR), `emitida_en`, `valida_hasta`. |
| POST | `/credencial/renovar` | token | Invalida el código anterior y emite otro (si se filtró una captura). |

Diseño (decidido: la valida recepción, no abre el torno):

- `codigo` = `NDC1.` + 40 caracteres aleatorios. **Opaco**: no lleva datos personales dentro, solo sirve para buscar. En la base se guarda su hash.
- Se valida **en el servidor** cuando alguien lo escanea, así que una membresía suspendida o vencida se rechaza en recepción aunque el teléfono tenga guardada una credencial vieja. Eso permite que el código viva mucho (hasta `fecha_fin` de la membresía, tope 35 días) y funcione **sin conexión** en el teléfono: la app lo guarda y lo refresca cuando hay red.
- Lo lee recepción desde el panel: `GET /accesos/credencial/{codigo}` (web, permiso `operar-checkins`) enseña foto, nombre, plan, vigencia y un semáforo. Queda en la bitácora.

### 6.10 Notificaciones push

| Método | Ruta | Permiso | Cuerpo |
|---|---|---|---|
| PUT | `/yo/push` | token | `expo_push_token`, `plataforma`. Se ata al token de Sanctum: al cerrar sesión o revocar el dispositivo, deja de recibir. |
| DELETE | `/yo/push` | token | La app lo llama si el usuario quita el permiso. |

Tabla nueva `dispositivos_push`. El envío va por el servicio de Expo (`https://exp.host/--/api/v2/push/send`) desde un canal de notificación propio (`App\Notifications\Canales\ExpoPush`), así que los avisos existentes solo añaden `'expo'` a su `via()`. Respeta las preferencias que ya existen (`notif_reservas`, `notif_membresia`, `notif_comunidad`). Los avisos (Fase 4 de la app): recordatorio de reserva, membresía por vencer, asesoría confirmada, factura lista.

### 6.11 Reportes (solo administración)

Grupo aparte: `auth:sanctum`, `ability:reportes`, `can:ver-reportes`, `movil.version`, `movil.no-suspendida`, `throttle:movil`. **Todo es lectura**; no hay ni un `POST` en este grupo.

| Método | Ruta | Respuesta `data` |
|---|---|---|
| GET | `/reportes/resumen?desde=&hasta=` | La portada: `rango{desde, hasta, etiqueta, dias}` y los totales de cada reporte (ocupación media, ingresos del periodo, tasa de no-show, miembros en riesgo) para las tarjetas grandes. |
| GET | `/reportes/ocupacion?desde=&hasta=` | Por espacio: `espacio`, `tipo`, `reservas`, `horas`, `capacidad_horas`, `ocupacion_pct`. Y por franja: `hora`, `reservas`, `pct`. |
| GET | `/reportes/consumo?desde=&hasta=` | Por plan y bolsa: `plan`, `bolsa`, `miembros`, `incluidas_por_miembro`, `usadas_total`, `promedio_por_miembro`, `aprovechamiento_pct`, `al_tope`. |
| GET | `/reportes/ingresos?desde=&hasta=` | `por_plan[]{plan, membresias, ingreso}`, `salones{eventos, ingreso, cobrado}`, `total_membresias`, `facturado_pagado`. |
| GET | `/reportes/no-show?desde=&hasta=` | `total_reservas`, `no_show`, `tasa_pct`, `horas_perdidas`, `por_miembro[]{miembro, faltas, horas}`. |
| GET | `/reportes/en-riesgo` | Miembros con membresía activa que dejaron de venir: `miembro`, `email`, `telefono`, `plan`, `vence`, `ultimo_acceso`, `dias_sin_venir`. |
| GET | `/reportes/{reporte}/csv?desde=&hasta=` | El mismo CSV que exporta la web, para mandarlo desde la hoja de compartir del teléfono. |

- **Mismos números que la web, mismo código.** Los cálculos de `ReportesController` (hoy métodos privados) se sacan a `App\Servicios\Reportes\GeneradorDeReportes`, que usan el controlador web y el de la API. Se suma al refactor del §3.
- Rango por omisión: el mismo de la web. Rango máximo por petición: 366 días.
- `en-riesgo` y `no-show` traen nombre, correo y teléfono de miembros: cada consulta queda en la bitácora (`EntradaBitacora`), agrupada por hora como ya se hace con los datos fiscales.
- En la app, esta cara tiene su propia navegación (Resumen · Ocupación · Ingresos · Miembros) y **no** muestra las pestañas del miembro. Si un admin también fuera miembro con otra cuenta, entra con esa otra cuenta.

### 6.12 Sin autenticación

| Método | Ruta | Respuesta `data` |
|---|---|---|
| GET | `/planes` | Planes públicos para la vista previa de quien no tiene cuenta: `id`, `nombre`, `subtitulo`, `precio`, `periodo_etiqueta`, `color`, `personas`, `incluye[]`, `beneficios[]`. Misma `DescripcionDelPlan` que «Mi membresía». |
| GET | `/estado` | `version_minima{ios, android}`, `version_recomendada`, `mantenimiento{activo, mensaje}`, `acceso{google, enlace_magico}` (qué botones pintar), `pagos{tarjeta, llave_publica, referencia}` y `web` (la URL base, para lo que se abre en el navegador). La app lo consulta al arrancar. |

---

## 7. «Mi seguridad» en la web

La pantalla `/seguridad` gana una sección **«Teléfonos con la app»**: nombre del dispositivo, último uso, desde cuándo, y un botón *Cerrar sesión en este teléfono* que revoca su token (y su registro push). Va detrás de `password.confirm` como el resto de acciones de esa pantalla, y queda en la bitácora de autenticación. Así, un teléfono perdido se desconecta desde cualquier computadora.

---

## 8. Pruebas (se escriben con la API, no después)

Carpeta `tests/Feature/ApiMovil/`. Las que importan:

1. **Aislamiento contra el JSON.** Con dos miembros, A intenta con su token leer, cancelar o descargar reservas, asesorías, órdenes y facturas de B, recurso por recurso → `404`, y B intacto. Y ninguna respuesta de A contiene el `id`, el correo ni el nombre de B (se busca en el cuerpo crudo).
2. **Token.** Sin token → 401. Tras `POST /auth/salir` el mismo token → 401. Revocado desde «Mi seguridad» → 401. 61 días sin uso → 401. Token de cuenta operativa imposible de obtener.
3. **Estados.** Suspendida → `403 cuenta_suspendida` en todas las rutas; sin verificar → `correo_sin_verificar`; consentimiento pendiente → `consentimiento_pendiente` salvo en las rutas ⚑.
4. **Reglas en el servidor.** Por la API: tope diario excedido, bolsa insuficiente, fuera de horario, día festivo, traslape, fecha fuera de vigencia, plan que no incluye la bolsa → `422` con el texto correcto y **sin** tocar el libro de horas.
5. **Idempotencia.** Dos `POST /reservas` con la misma `Idempotency-Key` → una sola reserva y la misma respuesta.
6. **Cancelación.** Con más de 2 h de margen devuelve horas y `devolvio_horas: true`; con menos, no y `false`; doble cancelación → `409` y las horas se devuelven una sola vez.
7. **Segundo factor.** Contraseña correcta con 2FA activo no da token sin el código; el `desafio` no sirve dos veces ni tras 5 minutos.
8. **Enlace mágico.** Canjear sin el secreto correcto falla; un solo uso; caduca a los 15 min.
9. **Google.** `id_token` con `aud` ajeno, caducado o con correo sin verificar → rechazado.
10. **Reportes.** Staff y caja no obtienen token. Un token `reportes` recibe `403 sin_permiso` en todas las rutas de miembro y un token `miembro` en todas las de reportes. Quitar `ver-reportes` a un admin corta su token en la siguiente petición. Los números de la API coinciden con los de la pantalla web para el mismo rango. Consultar `en-riesgo` deja entrada en la bitácora.
11. **Match.** El acompañante reserva contra la bolsa compartida y lo consumido baja para los dos; titular y acompañante pueden reservar 2 h cada uno el mismo día, pero ninguno 3 h; ninguno ve ni cancela las reservas del otro; el acompañante recibe `403 solo_titular` al renovar o cambiar de acompañante. En la web y en la API.
12. **Pagos.** `POST /pagos/tarjeta` no activa la membresía por sí solo (solo el webhook); `POST /pagos/referencia` con factura y sin datos fiscales → `422`; la misma `Idempotency-Key` no crea dos órdenes.
13. **Credencial.** Un código renovado deja de validar; una membresía suspendida o vencida sale en rojo en recepción aunque el código sea vigente.
14. **Regresión.** Toda la suite actual en verde tras el refactor del §3. Las pruebas del Match que hoy asuman el tope por membresía se actualizan a la regla nueva, y se dice cuáles.

---

## 9. Decisiones tomadas (22/09/2026)

| Tema | Decisión |
|---|---|
| Alcance | La app es el portal del miembro completo y, para administración, **solo reportes** (§6.11). Toda la operación se queda en la web. |
| Credencial | QR opaco que **recepción valida desde el panel** (§6.9). No abre el torno. |
| Sesión | El token caduca a los **60 días sin uso** (15 para reportes). |
| Contratar y renovar | **Dentro de la app**, con tarjeta (hoja de pago nativa de Stripe) o con referencia (§6.7). |
| Match | El acompañante reserva contra **la misma bolsa**; **tope diario por persona**; solo ve y maneja **lo suyo** (§6.5). Aplica también en la web. |
| Check-in manual | Fuera de la app: el acceso es por rostro. |

---

## 10. Orden de construcción (tras tu visto bueno)

1. Sanctum publicado + migración `personal_access_tokens` + `HasApiTokens`.
2. Refactor del §3, con la suite actual en verde.
3. Middlewares `movil.*`, formato de errores, límites, `/estado`.
4. Acceso (§5) con sus pruebas.
5. Rutas de lectura (§6.1–6.3, 6.5–6.8) con las pruebas de aislamiento.
6. Escritura: reservas con idempotencia, cancelar, asesorías, acompañante, Match.
7. Pagos dentro de la app (tarjeta y referencia).
8. Credencial (API + validación en el panel) y push.
9. Reportes (§6.11) con `GeneradorDeReportes` compartido.
10. «Mi seguridad» web con los teléfonos.
11. Suite completa + `/security-review` sobre la rama.

Después, el proyecto de Expo en `app-movil/`.

---

## 11. Lo que cambió al construirlo (22/09/2026)

- **Zona horaria del calendario (defecto previo, corregido también en la web).** Las horas de las reservas son hora de pared de Mérida, pero se interpretaban en la zona de la aplicación (UTC). Como `now()` es un instante real, todo lo que mide «cuánto falta» se corría seis horas: a las 13:00 de Mérida la tarde salía como pasada en la disponibilidad, el no-show de una reserva de las 10:00 podía marcarse de madrugada, la regla de las 2 h cortaba a las 2:00 en vez de a las 8:00, el tablero y la línea del día del panel veían ocupado lo que no, y el cierre automático de check-ins ponía la salida a las 13:00. Ahora todo pasa por `Reserva::zonaDelCalendario()` (`nodico.zona_horaria`) y los instantes salen con su zona (`…T16:00:00-06:00`). Prueba de regresión: `ReservasApiTest::test_a_mediodia_en_merida_…`.
- **Pendiente relacionado:** las consultas que usan `today()` (reservas «próximas», validación `after_or_equal:today`) siguen contando el día en UTC, así que entre las 18:00 y las 24:00 de Mérida el «hoy» del servidor ya es mañana. No se tocó para no cambiar más reglas de las pedidas; conviene decidir si se pasa `app.timezone` a Mérida o se ajustan esas consultas.
- **Tarjeta:** flujo en dos pasos para planes recurrentes (`/pagos/tarjeta` en modo `setup` → `/pagos/tarjeta/suscribir`), igual que la web con Elements, en lugar de crear la suscripción con el primer cobro pendiente.
- **Reservas del Match:** la disponibilidad contaba lo «usado ese día» por membresía y la reserva por persona; las dos cuentan ahora por persona.
- **Avisos push:** canal `App\Notifications\Canales\ExpoPush`, aviso genérico `AvisoDeLaApp` (respeta `notif_*`) y comando `nodico:avisos-push` cada 15 min (recordatorio una hora antes y membresía por vencer a 7 y 1 días). También salen al confirmar una asesoría y al enviar una factura.
- **Recepción:** pantalla `Accesos → Credencial` en el panel (`/accesos/credencial`), pensada para un lector de QR que teclea el código.
- **«Mi seguridad»:** sección «Teléfonos con la app» con cierre de sesión por teléfono (`DELETE /seguridad/telefonos/{id}`, detrás de `password.confirm`).
