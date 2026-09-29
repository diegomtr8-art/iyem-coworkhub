# Pagos con BBVA (Openpay) — Fase 0: reconocimiento

> Entregable de la Fase 0 de `PROMPT-PAGOS-BBVA.md`. **No hay código todavía.**
> Todo lo de aquí se verificó contra la documentación oficial el 29 de septiembre
> de 2026; cada afirmación lleva su enlace. Lo que la documentación **no** dice
> está en la sección [Lo que no pude confirmar](#lo-que-no-pude-confirmar), no
> rellenado con suposiciones.

---

## Estado de la implementación (29-sep-2026)

El instituto abrió una cuenta de **Openpay** (sandbox con Clientes,
Suscripciones, Tarjetas y Webhooks), así que la duda de la sección siguiente
quedó resuelta: se usa Openpay. Plan acordado con Diego:

| Paso | Qué | Estado |
|---|---|---|
| 1 | Pasarela Openpay: cobro con la tarjeta tecleada en Nódico (openpay.js + antifraude), 3-D Secure si el antifraude lo pide | **Hecho** |
| 2 | Cliente de Openpay por miembro; tarjeta guardada **en Openpay** en planes que se renuevan | **Hecho** |
| 3 | Suscripciones: planes en Openpay, alta al pagar, protección contra duplicados, cancelar/reactivar | Pendiente |
| 4 | Webhooks: Basic Auth + consulta del cargo antes de activar; renovación y rechazo automáticos | Pendiente |
| 5 | Revisión periódica de suscripciones canceladas por Openpay al agotar reintentos | Pendiente |
| 6 | App: la página de pago de Nódico dentro de la app | Pendiente (hoy abre el formulario de Openpay) |
| 7 | Pruebas en sandbox, incluida una renovación | Pendiente |

**Decisión de reintentos (Diego, 29-sep-2026):** 2 reintentos y la suscripción
queda `unpaid` (se reactiva cambiando la tarjeta); la membresía se suspende en
el primer rechazo, como hoy (`config/pagos.php` → `openpay.reintentos`,
`openpay.estado_tras_reintentos`).

**Cómo quedó el código** (Openpay y Ecommerce BBVA son la misma plataforma y
comparten código):

- `PAGOS_PASARELA=openpay` inyecta `PasarelaOpenpay('openpay')`; `bbva` la
  misma clase con las llaves y direcciones de BBVA. Stripe queda intacto.
- `Openpay/ClienteOpenpay`: HTTP sin SDK; cargos a nivel comercio o cliente,
  clientes (con recuperación por `external_id` si responde 2003) y tarjetas con
  token.
- `clientes_pasarela`: el cliente de cada miembro por pasarela y la tarjeta
  guardada (solo su id, marca, últimos 4 y vencimiento; la tarjeta vive en
  Openpay). `external_id = nodico-{entorno}-{usuario}` porque varios entornos
  comparten el sandbox.
- **3-D Secure** (`OPENPAY_3DS`): `si_hace_falta` cobra sin autenticación y,
  si el antifraude rechaza por riesgo (3005), reintenta con 3-D Secure, la
  misma tarjeta y un `order_id` nuevo; `siempre` lo pide en todos.
- La confirmación no cambió: `ConfirmadorDeCargo` consulta el cargo (por la
  ruta del cliente si es de un cliente) y compara importe, `order_id` y moneda.
- Pruebas: `tests/Feature/Pagos/PagoOpenpayTest.php`.

---

## ⚠️ Antes que nada: hay dos productos de BBVA, y solo uno sirve para esto

La dirección que da el encargo, `https://bbva-docs.openpay.mx/`, **no es la
documentación de Openpay**. Su certificado es de `docs.ecommercebbva.com` y lo que
sirve es la API de **«BBVA eCommerce»**, otro producto:

| | **BBVA eCommerce** | **Openpay** |
|---|---|---|
| Documentación | [bbva-docs.openpay.mx](https://bbva-docs.openpay.mx/) (= `docs.ecommercebbva.com`) | [documents.openpay.mx/docs/api](https://documents.openpay.mx/docs/api/index.html) |
| API sandbox / producción | `sand-api.ecommercebbva.com` / `api.ecommercebbva.com` | `sandbox-api.openpay.mx` / `api.openpay.mx` |
| Panel | `sand-portal.ecommercebbva.com` | `sandbox-dashboard.openpay.mx` |
| SDK PHP | [EcommerceBBVA/BBVA-PHP](https://github.com/EcommerceBBVA/BBVA-PHP) (`Bbva::getInstance`) | [open-pay/openpay-php](https://github.com/open-pay/openpay-php) (`Openpay::getInstance`) |
| Dato propio | Pide `affiliation_bbva` (número de afiliación) en cada cargo | Pide llave **pública** para tokenizar en el navegador |
| Recursos de la API | **Solo cargos**: crear, confirmar, devolver, obtener ([API Endpoints](https://bbva-docs.openpay.mx/#api-endpoints)) | Cargos, clientes, tarjetas, tokens, **planes, suscripciones, webhooks**, facturación CFDI |
| Captura de tarjeta | Formulario de BBVA por redirección (VPOS), o tarjeta en claro desde el servidor con autorización del ejecutivo ([Guía rápida](https://bbva-docs.openpay.mx/#cargos-con-tarjeta-sin-vpos)) | Token en el navegador con Openpay.js |

**Consecuencia directa:** la documentación de BBVA eCommerce **no tiene planes,
suscripciones, tarjetas guardadas ni webhooks**. Con ese producto **no se puede
conservar el cobro automático cada periodo**, que es requisito del encargo; cada
mes habría que mandar a la persona a pagar otra vez. Además su «cargo sin VPOS»
manda número y CVV **por nuestro servidor**, lo que saca a Nódico del alcance PCI
mínimo que hoy tiene con Stripe.

Todo lo que sigue asume **Openpay** (el de `documents.openpay.mx`), que es el único
de los dos que cumple el encargo. **Hay que confirmar que las credenciales del
instituto son de Openpay y no de BBVA eCommerce** — ver [pregunta 1](#preguntas-para-decidir).
Cómo distinguirlas: si el panel donde se ven las llaves es `dashboard.openpay.mx`
y hay una llave que empieza con `pk_`, es Openpay; si el panel es
`portal.ecommercebbva.com` y les dieron un **número de afiliación**, es BBVA
eCommerce.

---

## 1. El SDK de PHP

- Paquete vigente: **`openpay/sdk`** en Packagist, versión **3.1.1 del 28-oct-2024**
  ([Packagist](https://packagist.org/packages/openpay/sdk),
  [repositorio](https://github.com/open-pay/openpay-php)). La página de
  [librerías](https://documents.openpay.mx/docs/libraries) lo enlaza como el oficial.
- Soporte de PHP declarado: **`php >= 5.2.1`**, con `ext-curl` y `ext-hash`. Es
  decir, no declara ni prueba PHP 8.2. El README sigue diciendo «PHP 5.2 or later».
- Mantenimiento: repositorio no archivado, pero el último empuje fue el
  28-oct-2024 (casi dos años) y tiene 20 incidencias abiertas. Poco activo.
- El SDK es de estilo singleton global (`Openpay::getInstance`, `Openpay::setProductionMode`),
  sin tipos ni excepciones propias modernas.

**Decisión propuesta: no usar el SDK; hablar con la API por HTTP con el cliente de
Laravel (`Http::`).** Por qué:

1. La API es REST + JSON con **HTTP Basic** (llave privada como usuario, contraseña
   vacía) ([Autenticación](https://documents.openpay.mx/docs/api/index.html#autenticaci-n)).
   Son `Http::withBasicAuth($llave, '')->post(...)`: el SDK no ahorra casi nada.
2. Usamos pocos recursos (clientes, tarjetas, cargos, planes, suscripciones).
3. `Http::fake()` permite la **prueba del importe exacto** que pide la Fase 7 sin
   mocks de un singleton estático.
4. La API exige la cabecera **`X-Forwarded-For`** con la IP del dispositivo del
   cliente en toda llamada «por disposiciones oficiales para la prevención de
   fraude» ([Cargos](https://documents.openpay.mx/docs/api/index.html#cargos)); con
   `Http::` se pone explícita y se ve en el código.
5. No metemos una dependencia que no declara soporte para nuestra versión de PHP.

## 2. Cobrar una tarjeta sin que los datos pasen por Nódico

Flujo documentado ([Openpay.js](https://documents.openpay.mx/docs/openpay-js),
[Anti-Fraudes](https://documents.openpay.mx/docs/fraud-tool),
[Suscripciones](https://documents.openpay.mx/docs/suscriptions)):

1. La página carga **dos** scripts:
   `https://js.openpay.mx/openpay.v1.min.js` y
   `https://js.openpay.mx/openpay-data.v1.min.js`.
2. Se configura con **ID de comercio + llave pública**:
   `OpenPay.setId(...)`, `OpenPay.setApiKey(...)`, `OpenPay.setSandboxMode(true|false)`.
   La llave privada nunca va al navegador: la documentación lo dice expresamente.
3. El navegador llama a `OpenPay.token.create({...}, ok, error)` (o
   `extractFormAndCreate` con inputs marcados `data-openpay-card`). Los datos van
   **directo a Openpay** y vuelve un **token** (`id` tipo `tok...`).
4. El token es **de un solo uso**, se crea a nivel comercio y no está ligado a
   ningún cliente ([Tokens](https://documents.openpay.mx/docs/api/index.html#tokens)).
5. El navegador manda a Nódico solo `token_id` + `device_session_id`; el servidor
   cobra con `source_id = token` o lo guarda como tarjeta del cliente.

**Diferencia con Stripe Elements:** aquí **no hay iframe**. Los campos de tarjeta
son inputs de *nuestra* página; el JS de Openpay los lee y los envía. Los datos no
llegan a nuestro servidor, pero sí pasan por el DOM de nuestra página. Es el modelo
de tokenización por JavaScript (no el de campos alojados), y puede tener
implicaciones en el cuestionario PCI que aplique; no lo pude confirmar en la
documentación de Openpay.

### El `device_session_id`

- **Qué es:** el identificador del dispositivo que genera la herramienta
  antifraude de Openpay. Viene incluida en todas las cuentas sin costo
  ([Anti-Fraudes](https://documents.openpay.mx/docs/fraud-tool)).
- **De dónde sale:** `OpenPay.deviceData.setup("formId", "nombreCampoOculto")`
  (lo mete en un campo oculto del formulario) o `var id = OpenPay.deviceData.setup()`
  para mandarlo por AJAX. **Hay que llamar antes a `setSandboxMode()`**, porque
  `openpay-data.js` depende de `openpay.js`.
- En Android/iOS lo generan sus SDK nativos (`getDeviceCollectorDefaultImpl().setup()`
  / `createDeviceSessionId`).
- **Obligatorio:** en el cargo con token es `string (requerido, longitud = 32)`
  ([Con id de tarjeta o token](https://documents.openpay.mx/docs/api/index.html#con-id-de-tarjeta-o-token)).
  También se manda al asociar un token a un cliente
  ([guía de suscripciones](https://documents.openpay.mx/docs/suscriptions)).
- **Qué pasa si falta:** la documentación no lo dice literalmente. Siendo campo
  requerido, lo esperable es un error `1001`/`1003`, pero **no está confirmado**.
  Cuando el antifraude rechaza, la respuesta es `error_code 3005`, «The card was
  declined by fraud system», y según Openpay no hay forma de aprobarla: solo pedir
  otra tarjeta.

## 3. 3-D Secure

Fuente: [3D Secure](https://documents.openpay.mx/docs/three-d-secure).

- **Cómo se activa:** en el cargo, `"use_3d_secure": true` y `"redirect_url"`. En
  la referencia de la API, `redirect_url` «solo se puede utilizar si se especifica
  el manejo de 3D Secure».
- **Respuesta:** la transacción vuelve con `"status": "charge_pending"` y
  `payment_method: { "type": "redirect", "url": ".../charges/{id}/redirect/" }`.
  Hay que **guardar el `id`** y mandar a la persona a esa `url`.
- **Regreso:** el banco autentica, avisa a Openpay, y Openpay redirige a nuestra
  `redirect_url` añadiendo `?id={id_transaccion}`.
- **Qué hace Nódico al volver:** según la guía, el comercio **consulta el cargo por
  id** (`GET /charges/{id}`) y con eso decide la página de aceptado/rechazado.
  Nosotros mantendríamos la regla actual: la pantalla de regreso solo *consulta*;
  la membresía la activa el webhook `charge.succeeded`.
- **Estados posibles** ([Objeto Transaction Status](https://documents.openpay.mx/docs/api/index.html#objeto-transaction-status)):
  `IN_PROGRESS`, `COMPLETED`, `REFUNDED`, `CHARGEBACK_PENDING`,
  `CHARGEBACK_ACCEPTED`, `CHARGEBACK_ADJUSTMENT`, `CHARGE_PENDING`, `CANCELLED`,
  `FAILED`. El que interesa en 3DS es `charge_pending`: autenticación sin terminar.
- **Si la persona cierra la pestaña a media autenticación:** la documentación no
  dice cuánto dura un `charge_pending` ni si pasa a `cancelled` sola. No está
  confirmado; se prueba en sandbox en la Fase 2.
- **Token y 3DS:** si un cargo sin 3DS fue rechazado con `3005`, se puede reusar
  el mismo token para reintentarlo con 3DS.
- Existe además «[Autenticación selectiva](https://documents.openpay.mx/docs/selective-auth)»
  (Openpay decide cuándo pedir 3DS). No la revisé a fondo: queda para decidir con
  la cuenta real.

## 4. Planes y suscripciones

Fuentes: [Planes](https://documents.openpay.mx/docs/api/index.html#planes),
[Suscripciones](https://documents.openpay.mx/docs/api/index.html#suscripciones),
[guía](https://documents.openpay.mx/docs/suscriptions).

**Secuencia completa:**

1. **Plan** (una vez por plan de Nódico): `POST /plans` con `name`, `amount`,
   `repeat_every`, `repeat_unit` (`week` | `month` | `year`), `retry_times`,
   `status_after_retry` (`unpaid` | `cancelled`) y `trial_days`. Se puede crear por
   API o en el panel.
2. **Cliente:** `POST /customers` con nombre y correo. Su `id` va en
   `pasarela_cliente_id`.
3. **Tarjeta guardada:** `POST /customers/{id}/cards` con `token_id` +
   `device_session_id`. Devuelve la tarjeta con su `id`.
4. **Suscripción:** `POST /customers/{id}/subscriptions` con `plan_id` y
   `source_id` (id de tarjeta guardada o token), más `trial_end_date` si aplica.

**Cobros y reintentos:**

- La suscripción queda `trial` si hay días de prueba; si no, o cuando se cobra el
  primer pago, pasa a `active`.
- Si un cobro falla pasa a **`past_due`**. Openpay reintenta **solo**, tantas
  veces como diga `retry_times` del plan. Al agotarse, la deja en `unpaid` o
  `cancelled` según `status_after_retry`.
- Para reactivar una `unpaid` hay que **cambiar la tarjeta** de la suscripción
  (`PUT` con `source_id`/`card`).
- **Quién reintenta:** Openpay. Nódico **no** reintenta encima.
- La documentación **no dice cada cuánto** reintenta.

**Cancelar:**

- `DELETE /customers/{id}/subscriptions/{id}` cancela **inmediatamente**: «ya no
  se realizarán más cargos a la tarjeta y todos los cargos pendientes se
  cancelarán».
- `PUT` con `cancel_at_period_end: true` la cancela **al terminar el periodo**.
  Esto equivale al `cancel()` actual de Cashier (sigue con acceso hasta el fin de
  lo pagado).
- «Reactivar» (el `resume()` que usa hoy la app) sería `PUT` con
  `cancel_at_period_end: false`. Es plausible porque el campo es editable, pero
  **no está confirmado** que Openpay lo acepte después de haberlo puesto en `true`.

**Planes de Nódico:** Nodo Pro y Nodo Match serían planes de Openpay (mensual,
`repeat_every: 1`, `repeat_unit: month`, `trial_days: 0`). Day-Pass y Nódico Flex
son pago único: **no** necesitan plan en Openpay, solo cargo. La columna neutra
de `planes` solo se llenaría para los recurrentes.

**3DS en suscripciones:** la documentación de suscripciones **no menciona 3-D
Secure** y el alta no tiene `use_3d_secure` ni `redirect_url`. No está claro si el
banco puede pedir autenticación en el primer cobro de una suscripción ni cómo se
recibe. Ver [Lo que no pude confirmar](#lo-que-no-pude-confirmar).

## 5. Webhooks

Fuentes: [Notificaciones](https://documents.openpay.mx/docs/webhooks),
[Webhooks en la API](https://documents.openpay.mx/docs/api/index.html#webhooks).

### Cómo se verifica que viene de Openpay — el punto crítico

**Openpay no firma las notificaciones.** No hay HMAC ni secreto compartido como
el `whsec_` de Stripe. Lo único que ofrece es:

1. **HTTP Basic en el webhook:** al registrarlo se le da `user` y `password`, y
   Openpay los manda en cada POST. «Actualmente solo se soporta autenticación HTTP
   Basic.»
2. **Verificación de propiedad al registrar:** Openpay manda un POST
   `{"type": "verification", "verification_code": "..."}`, y ese código se pega en
   el panel para activar el webhook. Esto prueba que la URL es nuestra; **no**
   autentica los mensajes que llegan después.
3. **Requisitos del endpoint:** solo dominios (no IPs), HTTPS con TLS 1.2 y
   certificado de CA pública, puertos 443/8443/10443 (la guía añade 1518/1519).

**Por eso propongo dos cerrojos**, y que el webhook no se despliegue sin los dos:

- **(a) Basic Auth obligatorio**, con usuario y contraseña largos y aleatorios en
  `.env`, comparados con `hash_equals`. Sin ellos configurados, el endpoint
  rechaza todo, igual que hoy sin `STRIPE_WEBHOOK_SECRET`.
- **(b) Nunca creerle al cuerpo del mensaje.** Del webhook solo se toma el **id de
  la transacción**. Con la llave privada se consulta `GET /charges/{id}` (o
  `GET /customers/{c}/charges/{id}`) y se decide con **lo que responde Openpay**:
  estado, importe, cliente. Así, aunque alguien adivine o robe el Basic Auth, un
  webhook falso con un id inventado no activa nada. Esto es decisión nuestra, no
  un requisito de la documentación, aunque es lo que la guía de 3DS recomienda
  para la página de regreso.

Openpay no documenta una lista de IPs de origen.

### Entrega y reintentos

- Cuerpo: `type`, `event_date`, `transaction` (el objeto transacción completo) y
  `verification_code` (solo en `verification`).
- «Openpay intentará entregar la notificación hasta recibir una respuesta de
  éxito. Esto puede causar que algunas notificaciones se envíen dos veces». Hay
  que responder **200** siempre, o reintenta sin parar. La **idempotencia** de
  `procesarUnaVez` sigue siendo obligatoria.
- **La notificación no trae un id de evento propio.** La clave de idempotencia
  tendría que ser `type + transaction.id` (por ejemplo,
  `charge.succeeded:tr123...`).

### Eventos y los que necesitamos

La [referencia](https://documents.openpay.mx/docs/api/index.html#webhooks) lista,
entre otros: `charge.created`, `charge.succeeded`, `charge.failed`,
`charge.cancelled`, `charge.refunded`, `charge.rescored.to.decline`,
`subscription.charge.failed`, `chargeback.created|accepted|rejected`, más payouts,
transferencias, SPEI y órdenes. La guía de notificaciones da una lista más corta
(no incluye `charge.failed` ni `subscription.charge.failed`). **Las dos fuentes
oficiales no coinciden.**

| Evento Openpay | Qué haría Nódico |
|---|---|
| `verification` | Registrar el código en el log para pegarlo en el panel. No activa nada. |
| `charge.succeeded` | Consultar el cargo → `activar` (pago único o primer cobro) o `renovar` (cobro recurrente) |
| `subscription.charge.failed` | `suspenderPorImpago` |
| `charge.failed` / `charge.rescored.to.decline` | Registrar. No suspender por un intento de pago único fallido. |
| `charge.refunded`, `chargeback.*` | Registrar y avisar a caja. Hoy Stripe tampoco hace nada automático con esto. |
| cualquier otro | 200 y registrar |

**Lo que no existe:** no hay evento `subscription.created`, `subscription.cancelled`
ni `subscription.succeeded`. Consecuencias:

- El alta y las renovaciones llegan como **`charge.succeeded`**. La documentación
  **no dice** si la transacción de un cobro de suscripción trae `subscription_id`
  o algo para distinguirla de un pago único. Hay que verlo en sandbox.
- El fin de una suscripción (cancelada o `unpaid` al agotar reintentos) **no se
  notifica**. `detenerRenovacion` tendría que dispararse al cancelar desde Nódico,
  y un proceso programado tendría que consultar las suscripciones para detectar
  las que Openpay canceló sola.

## 6. Sandbox

Fuente: [Pruebas](https://documents.openpay.mx/docs/testing).

- **Se distingue por URL y por llaves:** `https://sandbox-api.openpay.mx` contra
  `https://api.openpay.mx`. Las credenciales de sandbox se generan al registrarse;
  las de producción, al aprobar la solicitud
  ([API Endpoints](https://documents.openpay.mx/docs/api/index.html#api-endpoints)).
  En el navegador: `OpenPay.setSandboxMode(true)`.
- **Tarjetas que aprueban:** `4111111111111111` (Visa), `5555555555554444` y
  `5105105105105100` (MasterCard), `345678000000007`, `341111111111111` y
  `343434343434343` (Amex), y tres Carnet. Cualquier vigencia futura; CVV de 3
  dígitos, o de 4 en Amex.
- **Tarjetas que rechazan:** `4222222222222220` → 3001 declinada;
  `4000000000000069` → 3002 expirada; `4444444444444448` → 3003 sin fondos;
  `4000000000000119` → 3004 robada; `4000000000000044` → 3005 antifraude.
- **Solo se pueden guardar** (para suscripciones) ocho tarjetas concretas. Ojo:
  **`4444444444444448` se puede guardar pero no tiene fondos**, útil para simular
  un cobro recurrente rechazado.
- **3-D Secure:** `5454545454545454` (MasterCard, HSBC) se aprueba con 3DS y es
  rechazada por antifraude sin él.
- **Webhooks en sandbox:** se envían «tal como si se tratará de un ambiente
  productivo».
- **No documentado:** cómo simular que la persona **falla** la autenticación 3DS o
  la abandona, ni cómo adelantar el reloj para forzar una renovación en sandbox
  (Stripe tiene *test clocks*; Openpay no menciona nada similar).

## 7. Importes — el error más caro

**Openpay recibe PESOS con hasta dos decimales, no centavos.**

- Cargos: `amount` es «numeric (requerido). Cantidad del cargo. Debe ser una
  cantidad mayor a cero, con hasta dos dígitos decimales»
  ([Con id de tarjeta o token](https://documents.openpay.mx/docs/api/index.html#con-id-de-tarjeta-o-token)).
  El ejemplo de 3DS manda `"amount": 6000.00`.
- Planes: `amount` es «Monto que se aplicará cada vez que se cobre la suscripción.
  Debe ser una cantidad mayor a cero, con hasta 2 dígitos decimales»
  ([Planes](https://documents.openpay.mx/docs/api/index.html#planes)). Los ejemplos
  usan `150.00` y `99.99`.
- Moneda: `MXN` o `USD`; por defecto `MXN`.

**Conversión:**

```php
// Stripe (hoy):   (int) round($plan->precio * 100)   → 599.00 se manda como 59900
// Openpay:        round((float) $plan->precio, 2)     → 599.00 se manda como 599.0
```

Por qué `round(..., 2)`: `planes.precio` es `decimal(10,2)` en la base de datos,
así que ya trae dos decimales exactos. El modelo lo convierte a `float`, y el
`round` evita arrastrar un error binario de coma flotante (p. ej. `79.00000000001`).
Con eso, `json_encode` lo manda como número con a lo más dos decimales.

Si por error se reusara la conversión de Stripe, Nodo Pro se cobraría **$59,900**
en vez de $599. La prueba de la Fase 7 debe fijar, para los cuatro planes,
exactamente `79`, `249`, `599` y `799` en el cuerpo que se manda a Openpay. Y
`SincronizarPreciosStripe` hoy multiplica por 100 al crear precios: su reescritura
no puede copiar esa línea.

## 8. La pregunta de negocio: ¿se puede facturar un pago con tarjeta?

**Lo que sí dice la documentación:**

- **Openpay tiene una API de facturación electrónica CFDI 4.0**
  ([Facturación electrónica](https://documents.openpay.mx/docs/electronic-invoice)):
  `POST /v1/{merchant}/invoices/v40` genera la factura **del comercio a su
  cliente**, asíncrona y con aviso por webhook (`invoice.*`). Puede ligarse a la
  transacción (`openpay_transaction_id`) y acepta `forma_pago` según el catálogo
  del SAT; el ejemplo usa `"28"`, tarjeta de débito.
- Para usarla en producción hay que **activarla con Openpay** y entregarles la
  **información fiscal del emisor, el CSD** (llave privada, pública y contraseña
  del SAT) y el logotipo.

**Lo que no puedo determinar, y no me toca decidir:**

- Desde el punto de vista de la pasarela, **nada impide facturar un pago con
  tarjeta**: Openpay incluso ofrece emitir la factura. La restricción «con tarjeta
  no hay factura» no viene de ninguna regla técnica que haya encontrado; parece
  una decisión operativa tomada con Stripe.
- Hoy Nódico **no emite** facturas: guarda los datos fiscales y **contabilidad**
  las emite a mano (ver `DatosFiscales.vue`). Con Openpay hay dos caminos:
  - **(A)** Contabilidad también factura los pagos con tarjeta, con el mismo
    flujo que hoy usan las órdenes de referencia. No requiere nada de Openpay.
  - **(B)** Nódico emite el CFDI por la API de Openpay, lo que implica entregar el
    CSD del instituto a Openpay.
- Cuál aplica, o si se mantiene «tarjeta = sin factura», es **decisión del
  instituto con su contabilidad**. Mientras no se decida, no se toca ningún texto.

**Textos que dependen de esa respuesta** (para la Fase 5):

- `resources/js/Pages/Portal/MiMembresia.vue:130` — «no generan factura»
- `resources/js/Pages/Portal/ElegirMetodoPago.vue:52` — «No genera factura.»
- `resources/js/Pages/Membresias.vue:38` — «El cobro se procesa por Stripe…»
- `app-movil/src/app/(miembro)/pagar.tsx:210` — «Se activa al instante. Sin factura.»
- y los que mencionen Stripe en `docs/`, correos y `DatosFiscales.vue`.

## 9. Otros hallazgos que afectan al plan

- **App móvil:** Openpay no tiene SDK oficial de React Native. Los SDK nativos
  oficiales ([Android](https://github.com/open-pay/openpay-android),
  [iOS](https://github.com/open-pay/openpay-ios)) no se tocan desde 2022. En npm
  solo hay `openpay-react-native` 2.0.10 (agosto de 2021), de un particular.
  Recomendación para la Fase 5: una **vista web** (WebView) de la página de pago
  del portal dentro de la app. Así reutiliza openpay.js, el `device_session_id` y
  el regreso de 3DS sin código nativo. Funciona en Expo Go.
- **`X-Forwarded-For` obligatorio** en llamadas al API. Detrás del proxy de
  Hostinger hay que confirmar que `$request->ip()` da la IP real del cliente
  (`TrustProxies`).
- **`order_id`** debe ser único entre todas las transacciones; si se repite,
  responde `1006 409 Conflict`. Sirve como **candado contra dobles cobros de pago
  único** si se deriva de algo estable. No hay equivalente para suscripciones: ahí
  la protección sigue siendo nuestra `suscripcionEnCurso()`, que consultaría la
  suscripción guardada en Openpay.
- **Diferencia en el campo de la tarjeta al suscribir:** la referencia dice
  `source_id` y la guía de ejemplo usa `card_id`. Nos quedamos con `source_id`
  (la referencia) y lo verificamos en sandbox.
- **Errores de tarjeta** ([Códigos de error](https://documents.openpay.mx/docs/api/index.html#c-digos-de-error)),
  base para los mensajes en español de la Fase 2:

  | Código | Openpay | Mensaje propuesto para el miembro |
  |---|---|---|
  | 3001 | Declinada | Tu banco rechazó el pago. Prueba otra tarjeta o llama a tu banco. |
  | 3002 | Expirada | Tu tarjeta está vencida. Usa otra. |
  | 3003 | Sin fondos | La tarjeta no tiene saldo suficiente. Prueba con otra. |
  | 3004 / 3009 | Robada / perdida | No pudimos cobrar con esta tarjeta. Usa otra o paga por referencia. *(sin acusar)* |
  | 3005 | Antifraude | No pudimos procesar esta tarjeta. Usa otra o paga por referencia. |
  | 3006 | Operación no permitida | Esta tarjeta no permite este tipo de pago. Usa otra. |
  | 3008 | No soportada en línea | Tu tarjeta no está habilitada para compras en internet. Actívala en tu banco o usa otra. |
  | 3010 / 3011 | Restringida / retener | Tu banco bloqueó el pago. Llámalo antes de volver a intentarlo. |
  | 3012 | Requiere autorización | Tu banco pide que autorices este pago. Llámalo y vuelve a intentarlo. |
  | 2004 / 2005 / 2006 / 2009 | Número, fecha o CVV inválidos | Revisa el número, la fecha o el código de seguridad. |

---

## Lo que no pude confirmar

Nada de esto se va a suponer en el código; cada punto se prueba en sandbox o se
pregunta a soporte de Openpay (soporte@openpay.mx, (55) 97 55 35 59).

1. **Qué producto tiene contratado el instituto:** Openpay o BBVA eCommerce.
   Bloquea todo lo demás.
2. **3-D Secure en suscripciones:** si el banco puede pedirlo en el primer cobro,
   cómo se entera Nódico y cómo se completa.
3. **Si la transacción de un cobro de suscripción** (en `charge.succeeded` y en
   `GET /charges/{id}`) trae `subscription_id` u otro dato para distinguirla de un
   pago único y del primer cobro.
4. **Si el alta de una suscripción sin días de prueba cobra en el momento**, de
   forma síncrona (y devuelve error si se rechaza), o programa el cobro para
   después.
5. **Cada cuánto** reintenta Openpay un cobro recurrente fallido.
6. **Si existe algún aviso** cuando una suscripción queda `cancelled`/`unpaid` al
   agotar reintentos. La lista de eventos no tiene uno.
7. **Qué pasa con un `charge_pending` de 3DS abandonado:** cuánto dura, si pasa a
   `cancelled` y si eso dispara `charge.cancelled`.
8. **Qué error exacto** devuelve un cargo sin `device_session_id`.
9. **Si `cancel_at_period_end: false`** reactiva una suscripción ya marcada para
   cancelarse (el `resume()` de hoy).
10. **Cómo forzar una renovación en sandbox** para la prueba de «renovación
    recurrente» que exige la Fase 6.
11. **La lista definitiva de eventos de webhook:** la referencia y la guía no
    coinciden.
12. **Si Openpay publica IPs de origen** de los webhooks. No lo encontré.
13. **Alcance PCI** de la tokenización por JavaScript (inputs en nuestra página)
    frente al iframe de Stripe.
14. **Facturación con tarjeta:** decisión del instituto (ver punto 8).

## Preguntas para decidir

1. **¿Las credenciales del instituto son de Openpay** (panel `dashboard.openpay.mx`,
   con llave pública `pk_…`) **o de BBVA eCommerce** (panel
   `portal.ecommercebbva.com`, con número de afiliación)? Si son de BBVA eCommerce,
   el cobro automático no es posible con esa API y hay que replantear el encargo
   antes de la Fase 1.
2. **Factura con tarjeta:** ¿se mantiene «sin factura», la emite contabilidad como
   con las referencias (A), o se usa la API CFDI de Openpay (B)?
3. **¿Aprobado hablar con la API por HTTP** en vez de usar `openpay/sdk`?
4. **¿Aprobado el doble cerrojo del webhook**: Basic Auth más volver a consultar el
   cargo en Openpay antes de activar nada?
5. **Reintentos:** ¿cuántos (`retry_times`) y qué estado final
   (`status_after_retry`: `unpaid`, que permite reactivar cambiando la tarjeta, o
   `cancelled`)? Propuesta: 2 reintentos y `unpaid`. Nódico suspende la membresía
   en el primer `subscription.charge.failed`, como hoy.
