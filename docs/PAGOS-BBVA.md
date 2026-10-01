# Pagos con Ecommerce BBVA — Fase 0: reconocimiento

> Entregable de la Fase 0 de `PROMPT-PAGOS-BBVA.md`. **No hay código todavía.**
> La documentación que manda es [docs.ecommercebbva.com](https://docs.ecommercebbva.com/)
> (la misma que sirve `bbva-docs.openpay.mx`), revisada el 29 de septiembre de
> 2026. Cada afirmación lleva su enlace. Lo que no está documentado se dice, no se
> deduce de Openpay.

## Comprobado en el sandbox el 1-oct-2026 (lee esto primero)

`prueba.nodico.com.mx` cobra con **`PAGOS_PASARELA=bbva`**, `BBVA_CAPTURA=vpos` y
`BBVA_SUSCRIPCIONES=false`. Las llaves siguen siendo las de sandbox del comercio
«Herencia Viva» hasta que BBVA entregue las de Nódico. Stripe sigue instalado e
inactivo.

Lo que se probó por la API, con las tarjetas de prueba publicadas y desde el
mismo código del portal:

| Prueba | Resultado |
|---|---|
| Cargo sin `affiliation_bbva` | Error 1003: la afiliación es obligatoria |
| Cargo con afiliación y token (con o sin `redirect_url` o `use_3d_secure`, en las dos URL) | Siempre `charge_pending` con la página de captura del banco (`card_capture`), que **vuelve a pedir la tarjeta** |
| Suscripción con tarjeta guardada y prueba vencida (renovación) | **Se cobra sola**, sin VPOS: `completed`, periodo 1 |
| Cargo esperando al banco | La persona ve «pendiente de autorización», **no un rechazo** (el fallo que tenía Stripe con 4000 0025 0000 3155 no se repite) |

**Conclusión.** Con este comercio, el primer pago **solo** puede hacerse en la
página del banco. Teclear la tarjeta en Nódico (`token`) obliga a teclearla dos
veces. La renovación automática funciona una vez guardada la tarjeta, pero para
guardarla hace falta el token, y el token no sirve para el primer cobro.

**Decisión de Diego (1-oct-2026):** mientras el ejecutivo no autorice el «cargo
sin VPOS», se paga en la página del banco y **sin renovación automática**. Cada
periodo se paga a mano, con los avisos de vencimiento. Encender
`BBVA_CAPTURA=token` y `BBVA_SUSCRIPCIONES=true` cuando BBVA lo autorice; el
código ya lo soporta (`tests/Feature/Pagos/SuscripcionesBbvaTest.php`).
Descartado: que la suscripción cobre el primer periodo, porque saltaría el
3-D Secure en el primer cobro.

### Pruebas manuales pendientes (las hace una persona, no Claude)

Pasan por la página del banco, donde se teclea la tarjeta. Con una cuenta de
prueba en `prueba.nodico.com.mx`, contratar un Day-Pass con cada tarjeta y anotar
lo que se ve al volver a Nódico:

| Caso | Tarjeta de prueba de Openpay | Esperado en Nódico |
|---|---|---|
| Aprobado | 4111 1111 1111 1111 | «¡Listo! Tu pago quedó confirmado» y membresía activa |
| Rechazado | 4000 0000 0000 0002 | Mensaje de rechazo en español; nada activado |
| Fondos insuficientes | 4000 0000 0000 0119 | Mensaje de fondos insuficientes |
| 3-D Secure completado | La que el simulador del banco autentique | Membresía activa al volver |
| 3-D Secure abandonado | Cerrar la pestaña en la autenticación | «Pendiente»; a las 24 h, «no se terminó» (nunca «rechazado») |

Fecha de vencimiento futura y cualquier CVV. Las tarjetas son las de la
documentación de Openpay; si el sandbox de BBVA usa otras, preguntar al
ejecutivo (pregunta 6 de abajo).

En el servidor queda la cuenta `pagos-sandbox@example.com` (usuario #54) con
cargos de esta comprobación. El #9 sirve para ver el abandono de 24 h.

## Estado: bloqueado por una respuesta del banco

Con lo que **documenta** Ecommerce BBVA se puede cobrar un pago único con 3-D
Secure. **No se puede cobrar solo cada mes**: no hay planes, suscripciones,
tarjetas guardadas ni webhooks.

Ya se le preguntó al ejecutivo si puede habilitar esas funciones en la cuenta
actual o si conviene migrar a Openpay. Según lo que conteste:

| Respuesta del ejecutivo | Qué documento manda | Camino |
|---|---|---|
| Habilita suscripciones en Ecommerce BBVA y da la documentación | Este, más lo que entregue | [Plan A](#plan-a--suscripciones-por-api-si-bbva-las-habilita) |
| Migrar a Openpay | [`PAGOS-OPENPAY.md`](PAGOS-OPENPAY.md) (ya verificado contra la doc de Openpay) | Suscripciones de Openpay |
| Ni lo uno ni lo otro | Este | [Plan B](#plan-b--renovación-de-un-toque) o [Plan C](#plan-c--recaptura-en-el-formulario-del-banco) |

Las Fases 1 (la interfaz `PasarelaDePagos`) y 2 (cobro único) sirven en los tres
casos. Se pueden empezar con visto bueno aunque el banco no haya contestado.

## Lo que dice el panel de sandbox (revisado el 29-sep-2026)

Revisión de solo lectura de `sand-portal.ecommercebbva.com`, con la sesión del
instituto. No se guardó ni se cambió nada, y ninguna llave se copió a este
documento: están en el panel, en **Administración → Comercios → (el comercio) →
Llaves API**, y van solo al `.env`.

- **Comercio:** «Herencia Viva», registrado el 17-jul-2025, estado *Activo*,
  sin límites de monto (diario, mensual ni por transacción).
- **Afiliación:** BANCOMER, visible en *Tarjetas → Resumen de Afiliación* y en la
  ficha del comercio. Es el `BBVA_AFILIACION` que faltaba.
- **Sitio web registrado:** `http://www.example.com`, un valor de relleno. Hay
  que corregirlo antes de pedir producción: la revisión mira el sitio.
- **Menú del panel:** Reportes; Administración (Comercios, Usuarios); Link de
  cobro BBVA; Tarjetas (Pagos, Reembolsos, Resumen de Afiliación);
  Desarrolladores (Logs, **Webhooks**, **Eventos**, Certificaciones). **No hay
  Planes, Suscripciones ni Clientes.**
- **Funcionalidades que se pueden certificar** (*Desarrolladores →
  Certificaciones → Iniciar nueva certificación*, sin guardar): *Card payment*
  (VPOS)\*, *3D Secure* (VPOS)\*, *Refund*\*, *Sale in DLLS*, *Months without
  interest*\* y *SKIP Payment*\*. Las marcadas con \* son obligatorias.
  **Suscripciones y tarjetas guardadas no aparecen:** el panel confirma lo que
  dice la documentación.
- **Webhooks: el menú existe, pero no hay botón para crearlos.** La lista está
  vacía y tiene los estados *Sin verificar / Verificado / Inactivo*, como en
  Openpay. *Eventos* ofrece la lista completa de Openpay, incluida
  «Subscripciones fallidas» y «Cargos con 3DS autenticada». Es la interfaz
  compartida de la plataforma: no prueba que este comercio pueda usarlos. Se le
  pregunta al ejecutivo si los puede dar de alta y cómo se autentican.
- **Requisitos para producción** (pantalla de inicio): que el sitio muestre la
  información de los productos o servicios que se pagan, y certificado SSL
  válido.
- **Contacto técnico que muestra el panel:** plataformas.especiales.mx@bbva.com.

## La tarjeta tecleada en Nódico (captura `token`)

A petición de Diego, para que la persona no salga a una página externa como con
Stripe. Se activa con `BBVA_CAPTURA=token` y `BBVA_LLAVE_PUBLICA`. Sin ellas se
usa el formulario del banco.

- **Por qué no se puede meter el formulario del banco en un iframe:** su página
  responde con `Content-Security-Policy: frame-ancestors https://*.openpay.mx`,
  así que el navegador lo bloquea fuera de `openpay.mx` (comprobado el
  29-sep-2026).
- **Cómo funciona:** `FormularioTarjetaBbva.vue` carga `openpay.v1.min.js` y
  `openpay-data.v1.min.js` de `js.openpay.mx`. Con el id de comercio y la llave
  pública crea un **token** en el navegador y obtiene el `device_session_id`
  del antifraude. A Nódico solo viajan esos dos datos: los campos no tienen
  `name` ni están en un `<form>`. El servidor cobra con `method: card`,
  `source_id: <token>`, `device_session_id`, `affiliation_bbva` y el importe en
  pesos (`PasarelaBbva::cobrarConToken`).
- **3-D Secure:** si el banco lo pide, la respuesta trae `payment_method.url` y
  se manda a la persona a la página de **su banco**. Esa autenticación es
  siempre del emisor y no se puede evitar. Si no lo pide, el cargo se consulta
  en el momento y se va directo a «confirmando».
- **La confirmación no cambia:** solo `GET /charges/{id}`, con las mismas
  comprobaciones de importe, `order_id` y dueño.
- **Lo que no está documentado:** la documentación de Ecommerce BBVA no describe
  los tokens. Es el «cargo sin VPOS» que el ejecutivo debe autorizar, y los
  campos (`source_id`, `device_session_id`) son los de Openpay. El sandbox sí
  expone `POST /tokens` con la llave pública (comprobado sin enviar datos de
  tarjeta). Si el comercio no lo tiene autorizado, el cobro fallará con un
  error de BBVA y la persona verá un mensaje en español.
- **App móvil:** sigue con el formulario del banco en el navegador del
  teléfono; openpay.js es para web.

## Lo que ya está construido (29-sep-2026)

Se eligió convivir con **un interruptor** (`PAGOS_PASARELA=stripe|bbva`): una
pasarela a la vez. BBVA se prueba en `prueba.nodico.com.mx` con llaves de
sandbox; producción sigue con Stripe.

| Pieza | Dónde |
|---|---|
| Interfaz común | `app/Servicios/Pagos/Contratos/PasarelaDePagos.php`; se elige en `AppServiceProvider` con `config/pagos.php` |
| Stripe, sin cambios de comportamiento | `PasarelaStripe` (antes `CobroConTarjeta`) |
| BBVA, formulario del banco (plan C) | `PasarelaBbva`, `Bbva/ClienteBbva` (HTTP, sin SDK) |
| Importe en pesos, en un solo lugar | `Importe::enPesos()`, con tope de cordura |
| Cargos propios | tabla `cargos_pasarela`, modelo `CargoPasarela` |
| Confirmación: la única puerta | `Bbva/ConfirmadorDeCargo` (consulta `GET /charges/{id}`, compara importe, `order_id` y moneda) |
| Idempotencia | `EventoPasarela` (antes `EventoStripe`; migración con copia de datos) |
| Regreso del banco | web `portal/pago/bbva/regreso`; app `pago/bbva/regreso-app` → `nodico://regreso-banco` |
| Cargos que nadie volvió a ver | `nodico:confirmar-cargos` cada 5 min; abandonados a las 24 h |
| Errores en español | `Bbva/MensajesDeBbva` |
| Pantallas | `Pago.vue` (rama BBVA y vista previa con motivo), `PagoConfirmando.vue` (fallido, pendiente, «Terminar el pago»), `ElegirMetodoPago.vue`, app `pagar.tsx` |
| Pruebas | `tests/Feature/Pagos/PagoBbvaTest.php` (importe exacto de los 4 planes, id inventado, id ajeno, importe distinto, repetidos, abandono, app) |

**Qué no se hizo, y por qué:**

- **No hay `pasarela_cliente_id`** en `users`: la documentación de BBVA no tiene
  API de clientes (el cargo lleva los datos de la persona, «no se creará una
  cuenta al cliente»). Se añade cuando el banco documente clientes y tarjetas.
- **No se renombró `stripe_price_id`** ni se reescribió `nodico:stripe-precios`:
  con BBVA no hay planes que sincronizar. Queda para el plan A o para la Fase 6.
- **Un plan recurrente con BBVA se paga por periodo.** «Renovar mi membresía»
  crea un cargo nuevo; si el mismo plan sigue vigente, se **extiende**
  (`renovar`), si no, se da de alta.
- **Los textos de «sin factura» no se tocaron** (decisión del instituto).

**Para activarlo en `prueba.nodico.com.mx`:** poner en el `.env` del servidor
`PAGOS_PASARELA=bbva`, `BBVA_MERCHANT_ID`, `BBVA_LLAVE_PRIVADA`,
`BBVA_AFILIACION` y `BBVA_SANDBOX=true`; correr `php artisan migrate` y
`php artisan config:cache`. Para volver: `PAGOS_PASARELA=stripe` y
`config:cache`.

**Pendiente de comprobar en el servidor:** qué IP sale en `$request->ip()`
detrás de Hostinger (§5), y el recorrido completo en sandbox: aprobado,
rechazado, 3-D Secure completo, abandonado y pestaña cerrada.

---

## 1. Lo verificado

| Punto | Lo que dice la documentación | Fuente |
|---|---|---|
| URL base | Sandbox `https://sand-api.ecommercebbva.com/`, producción `https://api.ecommercebbva.com/` | [API Endpoints](https://docs.ecommercebbva.com/#api-endpoints) |
| Rutas | `{base}/v1/{MERCHANT_ID}/{recurso}`. **Recursos listados: solo `charges` y `charges/{TRANSACTION_ID}`** | [API Endpoints](https://docs.ecommercebbva.com/#api-endpoints) |
| Autenticación | HTTP Basic: usuario = llave privada, contraseña vacía. Solo HTTPS | [Autenticación](https://docs.ecommercebbva.com/#autenticaci-n) |
| Llaves | **Solo se documenta la llave privada.** No hay llave pública ni tokenización en el navegador | [Autenticación](https://docs.ecommercebbva.com/#autenticaci-n) |
| Importe | `amount`: «Cantidad del cargo. Debe ser una cantidad mayor a cero, con hasta dos dígitos decimales». Pesos, no centavos | [Con VPOS](https://docs.ecommercebbva.com/#con-vpos) |
| Moneda | `MXN` o `USD` | [Con VPOS](https://docs.ecommercebbva.com/#con-vpos) |
| `X-Forwarded-For` | Obligatorio, con la IP del dispositivo del cliente, «por disposiciones oficiales para la prevención de fraude E-commerce» | [Cargos](https://docs.ecommercebbva.com/#cargos) |
| 3-D Secure y VPOS | «Todos los comercios nacen por default con autenticación 3d Secure y VPOS». `use_3d_secure` es TRUE por defecto; solo se manda FALSE si el comercio lo tiene habilitado | [Guía rápida](https://docs.ecommercebbva.com/#cargos-con-vpos), [Con VPOS](https://docs.ecommercebbva.com/#con-vpos) |
| Flujo VPOS | El cargo nace `charge_pending` con `payment_method.url` (`.../card_capture`). Ahí la persona teclea la tarjeta **en el formulario de BBVA**, pasa 3DS y vuelve a `redirect_url?id={transacción}` | [Guía rápida](https://docs.ecommercebbva.com/#cargos-con-vpos) |
| Confirmar | El comercio consulta `GET /v1/{MERCHANT_ID}/charges/{TRANSACTION_ID}` y genera el recibo «en base a la información obtenida» | [Obtener un cargo](https://docs.ecommercebbva.com/#obtener-un-cargo) |
| Estados | `IN_PROGRESS`, `COMPLETED`, `REFUNDED`, `CHARGE_PENDING`, `CANCELLED`, `FAILED` | [Transaction Status](https://docs.ecommercebbva.com/#objeto-transaction-status) |
| Devoluciones | `POST .../charges/{id}/refund`, total o parcial; tarda de 1 a 3 días hábiles en verse | [Devolver un cargo](https://docs.ecommercebbva.com/#devolver-un-cargo) |

### Campos obligatorios que no estaban en la tabla del encargo

Son de la petición «Con VPOS» ([Petición](https://docs.ecommercebbva.com/#con-vpos)):

- **`affiliation_bbva`** (string, **requerido**): «Debe contener el número de
  afiliación». Hace falta en `.env` (`BBVA_AFILIACION`), y hay que confirmar que
  el instituto tiene ese número para sandbox y para producción.
- **`order_id`** (string, **requerido**, hasta 100): «Debe ser único entre todas
  las transacciones». Si se repite: error `1006 409` «Ya existe una transacción
  con el mismo ID de orden».
- **`customer`** (objeto, **requerido**): nombre, apellidos, correo y teléfono.
  «No se creará una cuenta al cliente».
- **`redirect_url`** (string, **requerido**).
- `description` (string, requerido, hasta 250).

### Correcciones a la tabla del encargo

- **SDK de PHP:** sí está en Packagist, como
  [`bbva/sdk`](https://packagist.org/packages/bbva/sdk) →
  [EcommerceBBVA/BBVA-PHP](https://github.com/EcommerceBBVA/BBVA-PHP). Pero **no
  tiene versiones etiquetadas**, solo ramas (`dev-master`, 26-mar-2025), y declara
  `php >= 5.2.1`.
  **Propuesta: no usarlo** y hablar con la API con el cliente HTTP de Laravel. Son
  tres llamadas (crear, consultar y devolver un cargo) con HTTP Basic; se prueban
  con `Http::fake()`, que es lo que exige la prueba del importe exacto, y no
  dependemos de una rama sin versión.
- **`BBVA_LLAVE_PUBLICA`:** no aparece en la documentación y con VPOS no hace
  falta. No se agrega salvo que el banco diga otra cosa.
- **`POST /customers`:** **no está documentado.** La única mención es una frase de
  ejemplo en *API Endpoints* («si queremos crear un nuevo cliente, el endpoint
  sería…»), que viene de la documentación de Openpay. Hay un «Objeto Cliente» y
  algunos ejemplos del SDK usan `$bbva->customers->get()`, pero no hay operación
  para crear, consultar ni listar clientes.

### Incoherencias de la propia documentación

Para no tropezar con ellas:

- El ejemplo `curl` de *Obtener un cargo* apunta a `/customers/{id}` en vez de a
  `/charges/{id}`. La definición correcta es la de arriba (`GET .../charges/{TRANSACTION_ID}`).
- *Confirmar un cargo* habla de cargos creados con `capture = "false"`, pero ese
  campo **no aparece** en las peticiones de cargo de BBVA. No lo usaremos: cobro
  directo.
- El objeto de error menciona clases `OpenpayServiceException` / `OpenpayException`:
  es la herencia de Openpay.

---

## 2. Los tres huecos

### 2.1 Suscripciones recurrentes — **no existen en la documentación**

- La lista de recursos es solo `charges`. No hay `/plans` ni `/subscriptions`.
- El único «plan» es el **Objeto PaymentPlan**
  ([enlace](https://docs.ecommercebbva.com/#objeto-paymentplan)), que es **meses
  con o sin intereses** (`payments`, `payments_type`, `deferred_months`). No sirve
  para cobrar una membresía cada mes.
- **El panel de sandbox no lo revisé:** entrar pide la contraseña del instituto.
  Si alguien con acceso ve un menú de «Planes» o «Suscripciones», cambia todo.
  Vale la pena que lo mire quien tenga la cuenta.

**La tensión con 3-D Secure:** un cobro recurrente ocurre sin la persona
presente, y este comercio nace con 3DS obligatorio, que exige que la persona se
autentique. Las dos cosas no conviven sin que el banco configure algo expreso
(cobros iniciados por el comercio exentos de 3DS). La documentación ya exige
autorización del ejecutivo para algo menor: «Para utilizar el cargo sin Vpos se
debe solicitar autorización con su ejecutivo de cuenta»
([Guía rápida](https://docs.ecommercebbva.com/#cargos-con-tarjeta-sin-vpos)).

### 2.2 Webhooks — **no existen en la documentación**

No hay registro, ni lista de eventos, ni mecanismo de autenticidad. **No se
improvisa ninguna verificación.** El diseño se apoya en el patrón que la
documentación sí respalda: consultar el cargo por su id con la llave privada
(ver [sección 3](#3-diseño-propuesto-para-confirmar-pagos)).

### 2.3 Tarjeta guardada — **no documentada, pero hay rastros**

- No hay ninguna operación para guardar una tarjeta ni para cobrarle a una
  guardada.
- Existe un **Objeto Tarjeta** con `id` y `customer_id`
  ([enlace](https://docs.ecommercebbva.com/#objeto-tarjeta)), y el error **2002**,
  «La tarjeta con este número ya se encuentra registrada en el cliente»
  ([Almacenamiento](https://docs.ecommercebbva.com/#almacenamiento)). La
  plataforma de fondo sí guarda tarjetas, pero **la API publicada no dice cómo
  usarlo**.
- El flujo VPOS **no devuelve un id de tarjeta reutilizable**: devuelve la
  transacción, con la tarjeta enmascarada.

**Consecuencia importante:** la «renovación de un toque» (plan B del encargo)
**también depende de este hueco**. No se puede construir solo con lo documentado.
Por eso añado un plan C.

### Descartado a propósito: el «cargo sin VPOS»

El cargo «Con tarjeta» ([enlace](https://docs.ecommercebbva.com/#con-tarjeta))
manda número, CVV y vigencia **desde nuestro servidor**. Eso metería los datos de
tarjeta en Nódico y lo sacaría del alcance PCI mínimo que tiene hoy con Stripe.
Además requiere autorización del ejecutivo. **Nódico usa solo VPOS**, aunque el
banco lo habilite.

---

## 3. Diseño propuesto para confirmar pagos

Sin webhooks, **la única verdad es `GET /charges/{id}` consultado desde el
servidor con la llave privada**. Ni la URL de retorno, ni el navegador, ni el
usuario.

### El cargo pendiente queda registrado *antes* de mandar a la persona al banco

Al crear el cargo, Nódico guarda una fila propia (tabla nueva, p. ej.
`cargos_pasarela`) con: `transaccion_id` (de BBVA), `order_id` (nuestro),
`user_id`, `plan_id`, `importe` esperado, `tipo` (`alta` o `renovacion`), estado
y fecha. Esto cierra un hueco concreto:

> **Ataque del id ajeno:** alguien paga un Day-Pass de $79, toma ese id de
> transacción y lo pega en la URL de retorno del checkout de Nodo Pro. BBVA
> responde `COMPLETED` y, sin esta tabla, se activaría Nodo Pro por $79.

El confirmador solo acepta ids que **existen en esa tabla y pertenecen a esa
persona**. Además comprueba que lo que dice BBVA coincide con lo guardado:
`amount`, `order_id` y `currency`.

### `order_id`

`order_id = "nod-{id de cargos_pasarela}"`. BBVA lo exige único, así que un
reintento accidental de la **misma** fila choca con `1006` en vez de cobrar dos
veces. Es la versión BBVA de la protección de `suscripcionEnCurso()`, con esta
regla: **si la persona ya tiene un cargo `charge_pending` reciente del mismo
plan, se le devuelve a ese mismo `payment_method.url`** en vez de crear otro.

### Un confirmador único: `ConfirmadorDeCargo`

Es la única puerta por la que se activa una membresía pagada con tarjeta:

```
confirmar(transaccionId):
  fila   ← cargos_pasarela donde transaccion_id = id   (si no existe → rechazar, registrar)
  cargo  ← GET /charges/{id}                            (llave privada)
  si amount/order_id/currency no coinciden con la fila  → rechazar, registrar, alertar
  según cargo.status:
    COMPLETED       → EventoPasarela::procesarUnaVez("bbva:{id}:completed",
                         → activar() o renovar() según fila.tipo)
    FAILED          → marcar fila fallida; si era renovación → suspenderPorImpago()
    CANCELLED       → marcar fila cancelada; nada más
    CHARGE_PENDING  → nada (sigue esperando)
    IN_PROGRESS     → nada (sigue esperando)
    REFUNDED        → registrar y avisar a caja; no se revierte solo
  registrar siempre (id, estado, decisión)
```

`ActivadorDeMembresia` **no cambia**: se llama con las mismas firmas.
`EventoStripe` se generaliza a `EventoPasarela` conservando `procesarUnaVez`, con
la clave `bbva:{transacción}:{estado}`.

### Quién llama al confirmador

1. **La página de retorno** (`redirect_url?id=…`). Solo llama al confirmador y
   muestra «confirmando tu pago» (se reutiliza `PagoConfirmando.vue`). No decide
   nada; el `id` de la URL es una pista.
2. **Un proceso programado** (cada 5 minutos) que revisa las filas en
   `charge_pending` o `in_progress`. **No es opcional:** sin webhooks, si la
   persona paga y cierra la pestaña antes de volver, nadie más se entera.
3. **El portal y la app al abrir «Mi membresía»**, si la persona tiene una fila
   pendiente propia.

### Cargos abandonados

La documentación no dice cuánto vive un `charge_pending`. Propuesta:

- El proceso programado lo consulta durante **24 horas**.
- Si sigue igual, la fila se marca *abandonada* y ya no se consulta. El cargo en
  BBVA no se toca.
- Mientras tanto, el miembro ve «Tu pago está pendiente de autorización del
  banco» con dos botones: **Terminar el pago** (vuelve a `payment_method.url`) y
  **Empezar de nuevo**.
- Si pasadas las 24 horas BBVA lo marca `COMPLETED`, cualquier consulta
  posterior (la del portal, por ejemplo) lo activa igual, porque la fila existe.

---

## 4. La renovación: tres caminos

### Plan A — suscripciones por API, si BBVA las habilita

Depende enteramente de la documentación que entregue el ejecutivo. Si resulta
ser la de Openpay, [`PAGOS-OPENPAY.md`](PAGOS-OPENPAY.md) ya tiene el análisis
(planes, reintentos, cancelación al fin del periodo, eventos). Regla que se
mantiene: si el banco reintenta solo, **Nódico no reintenta encima**.

### Plan B — renovación de un toque

Requiere que el banco documente **guardar tarjeta y cobrarle después**. El aviso
de vencimiento ya existe (`nodico:avisos-push`: a 7 y a 1 día). Lleva a una
pantalla con el plan, el importe y la tarjeta terminada en ····1234, y un botón
**Renovar**. El cargo va contra la tarjeta guardada; si el banco pide 3DS, sigue
el flujo de la sección 3.

### Plan C — recaptura en el formulario del banco

**Se construye solo con lo documentado hoy.** Mismo aviso y misma pantalla, pero
**Renovar** crea un cargo VPOS nuevo y manda a la persona al formulario de BBVA a
teclear la tarjeta. Es lo más lejano a Stripe (la persona teclea la tarjeta cada
mes) y la membresía se vence si no paga. Pero funciona desde el día uno y usa
exactamente el mismo código que la Fase 2.

**Recomendación:** construir la Fase 2 de modo que el plan C salga casi gratis y
cambiar a A o B cuando el banco responda. Con A o C, Day-Pass y Flex (pago único)
no cambian.

---

## 5. La IP del cliente (`X-Forwarded-For`)

> **Resuelto el 29-sep-2026 en `prueba.nodico.com.mx`.** Hostinger pone la IP
> real en `REMOTE_ADDR`, pero deja pasar la cabecera del visitante: con
> `X-Forwarded-For: 1.2.3.4`, el servidor recibe `1.2.3.4, <IP real>` y
> `$request->ip()` devuelve **`1.2.3.4`**. `PasarelaBbva` usa ahora
> `REMOTE_ADDR` (prueba `test_una_ip_inventada_en_la_cabecera_no_llega_al_antifraude`).
> **Pendiente, fuera de pagos:** lo mismo afecta a todo lo que usa
> `$request->ip()` en el sitio, como los límites de intentos por IP. Conviene
> revisar `trustProxies` aparte.

`bootstrap/app.php` tiene `$middleware->trustProxies(at: '*')`: **Laravel confía
en cualquier `X-Forwarded-For` que llegue**. Dos escenarios, y no sé cuál es el
de Hostinger:

- **Si Hostinger pone un proxy delante** y escribe la cabecera,
  `$request->ip()` da la IP real. Bien.
- **Si no hay proxy** (LiteSpeed atiende directo), la cabecera la escribe **quien
  quiera**, y `$request->ip()` devuelve lo que diga el visitante. Un defraudador
  le mandaría a BBVA la IP que prefiera, y el antifraude del banco trabajaría con
  un dato falso sin que nada falle.

**Comprobación en la Fase 2, antes de dar nada por bueno:** una ruta temporal en
`prueba.nodico.com.mx` que muestre `REMOTE_ADDR`, `X-Forwarded-For` y
`$request->ip()`. Se abre desde un celular con datos móviles (IP conocida) y
luego con una cabecera `X-Forwarded-For` falsa por `curl`. Según el resultado,
`trustProxies` se restringe a las IPs reales del proxy de Hostinger, o se quita.

## 6. Importes

- Conversión, **en un solo lugar** (p. ej. `Importe::paraPasarela(Plane $plan)`):
  `round((float) $plan->precio, 2)`. `planes.precio` es `decimal(10,2)`; el
  `round` evita arrastrar error de coma flotante.
- Resultado: Day-Pass `79`, Flex `249`, Nodo Pro `599`, Match `799`, **en pesos**.
- El `(int) round($plan->precio * 100)` de Stripe vive hoy en `CobroConTarjeta` y
  en `SincronizarPreciosStripe`. **No se copia a nada de BBVA.**
- Prueba automática (Fase 7): con `Http::fake()`, afirmar el `amount` exacto del
  cuerpo enviado para los cuatro planes. Y además una salvaguarda en el código:
  rechazar cualquier importe mayor a 10 veces el precio del plan.

## 7. Errores del banco en español

Códigos de [Tarjetas](https://docs.ecommercebbva.com/#tarjetas) y
[Almacenamiento](https://docs.ecommercebbva.com/#almacenamiento). Con VPOS, casi
todos los ve la persona **en el formulario del banco**. A Nódico le llegan como
`FAILED` con `error_message` al consultar el cargo, y la pantalla de «confirmando»
los traduce:

| Código | BBVA | Mensaje para el miembro |
|---|---|---|
| 3001 | Declinada | Tu banco rechazó el pago. Prueba otra tarjeta o llama a tu banco. |
| 3002 | Expirada | Tu tarjeta está vencida. Usa otra. |
| 3003 | Sin fondos | La tarjeta no tiene saldo suficiente. Prueba con otra. |
| 3004 / 3009 | Robada / perdida | No pudimos cobrar con esta tarjeta. Usa otra o paga por referencia. *(sin acusar a nadie)* |
| 3005 | Fraudulenta | No pudimos procesar esta tarjeta. Usa otra o paga por referencia. |
| 3006 | Operación no permitida | Esta tarjeta no permite este tipo de pago. Usa otra. |
| 3008 | No soportada en línea | Tu tarjeta no está habilitada para compras en internet. Actívala en tu banco o usa otra. |
| 3010 / 3011 | Restringida / retener | Tu banco bloqueó el pago. Llámalo antes de volver a intentarlo. |
| 3012 | Requiere autorización | Tu banco pide que autorices este pago. Llámalo y vuelve a intentarlo. |
| 1006 | `order_id` repetido | *(interno: no se muestra; se retoma el cargo existente)* |

**No confirmado:** que `error_message` traiga el código numérico en un cargo VPOS
rechazado; puede traer solo texto. Si no trae código, se muestra un mensaje
genérico más el texto del banco.

## 8. Sandbox

- **Se distingue por URL base y por credenciales**: las de producción se generan
  al aprobar la solicitud ([API Endpoints](https://docs.ecommercebbva.com/#api-endpoints)).
- **Tarjetas de prueba: la documentación de BBVA no publica ninguna.** Solo dice,
  en el error `2007`, que «El número de tarjeta es de prueba, solamente puede
  usarse en Sandbox». Los ejemplos usan `4242424242424242` y `4111111111111111`,
  pero **no están documentadas como tarjetas de prueba**, ni cuáles rechazan o
  simulan 3DS. Hay que pedírselas al banco.

## 9. Factura con tarjeta

La documentación de Ecommerce BBVA **no menciona facturación**. Hoy Nódico no
emite facturas: guarda los datos fiscales y contabilidad las emite (ver
`DatosFiscales.vue`). Nada técnico de la pasarela impide que contabilidad facture
también los pagos con tarjeta. **Es decisión del instituto con su contabilidad.**
Mientras no se decida, no se toca ningún texto.

Textos que dependen de esa decisión (Fase 5):

- `resources/js/Pages/Portal/MiMembresia.vue:130` — «no generan factura»
- `resources/js/Pages/Portal/ElegirMetodoPago.vue:52` — «No genera factura.»
- `resources/js/Pages/Membresias.vue:38` — «El cobro se procesa por Stripe…»
- `app-movil/src/app/(miembro)/pagar.tsx:210` — «Se activa al instante. Sin factura.»

## 10. App móvil

Con VPOS, la tarjeta se teclea en el formulario de BBVA, así que la app no
necesita SDK. Propuesta para la Fase 5:

- Abrir `payment_method.url` en el navegador del sistema dentro de la app
  (`expo-web-browser`, `openAuthSessionAsync`), con `redirect_url` apuntando a una
  ruta de Nódico que regrese a `nodico://pago/confirmando?id=…`.
- Funciona en Expo Go.
- Se corrige la fila de `docs/APP-MOVIL.md` que dice que el pago con tarjeta
  funciona gracias al SDK de Stripe.

Queda por confirmar si el formulario de BBVA acepta abrirse dentro de una vista
web embebida o solo en el navegador (algunos bancos bloquean los iframes en 3DS).
Por eso la propuesta es el navegador del sistema, no una WebView.

---

## Lo que no pude confirmar

1. Si existen **suscripciones, tarjetas guardadas o webhooks** fuera de la
   documentación pública, o en el panel de sandbox, que no revisé.
2. El **número de afiliación** (`affiliation_bbva`) del instituto, en sandbox y
   producción.
3. **Tarjetas de prueba** de Ecommerce BBVA: cuáles aprueban, cuáles rechazan y
   cuáles simulan 3DS.
4. **Cuánto vive un `charge_pending`** abandonado y si pasa solo a `CANCELLED`.
5. Si **`error_message`** de un cargo fallido trae el código numérico.
6. Si el formulario VPOS funciona **dentro de una WebView**.
7. Qué IP entrega **Hostinger** en `$request->ip()` (se comprueba en la Fase 2).
8. Qué exige la **revisión para pasar a producción**, además de mostrar los
   servicios y tener SSL válido.
9. Si un **mismo `order_id`** rechazado (`FAILED`) se puede reintentar o exige
   uno nuevo.

## Preguntas para el ejecutivo de BBVA

Las tres primeras ya van en el WhatsApp. Esta es la lista completa, para un
correo que deje constancia:

1. ¿El comercio puede hacer **cobros recurrentes / suscripciones** por API? Si sí,
   ¿con qué endpoints, dónde está su documentación y qué hay que habilitar? Si no,
   ¿conviene migrar a una cuenta de **Openpay**?
2. ¿Se pueden **guardar tarjetas** y cobrarlas después sin la presencia del
   tarjetahabiente? ¿Con qué endpoints?
3. ¿Existen **notificaciones (webhooks)**? ¿Cómo se registran y cómo se verifica
   que vienen de BBVA?
4. En un cobro recurrente, ¿cómo se maneja el **3-D Secure**? ¿Hay que habilitar
   cobros iniciados por el comercio exentos de 3DS?
5. ¿Cuál es nuestro **número de afiliación** (`affiliation_bbva`) de sandbox y de
   producción?
6. ¿Qué **tarjetas de prueba** hay para sandbox (aprobada, rechazada, 3DS
   exitoso y 3DS fallido)?
7. ¿Cuánto tiempo sigue vivo un cargo en **`charge_pending`** si la persona
   abandona la autenticación? ¿Pasa solo a cancelado?
8. ¿La plataforma permite **emitir factura** de los pagos con tarjeta, o lo
   resolvemos por nuestra cuenta?
9. ¿Qué exige la **revisión para pasar a producción**, además de mostrar la
   información de los servicios y tener SSL válido?
10. **Comisión** por transacción y plazo de depósito a nuestra cuenta.

## Decisiones que necesito

1. **¿Visto bueno para empezar las Fases 1 y 2** (interfaz y cobro único con
   VPOS) mientras responde el banco? Sirven en los tres caminos.
2. **¿Aprobado hablar con la API por HTTP** en vez del SDK sin versión?
3. **¿Aprobado el diseño de confirmación**: tabla de cargos propios, confirmador
   único, proceso programado cada 5 minutos y 24 horas de espera?
4. **¿Plan C como respaldo** si el banco no habilita suscripciones ni tarjetas
   guardadas?
5. **Factura con tarjeta:** decisión del instituto con contabilidad.
