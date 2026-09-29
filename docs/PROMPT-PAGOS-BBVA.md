# Prompt para Claude Code — Cambiar el cobro de Stripe a BBVA (Openpay)

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico cobra hoy con **Stripe**, a través de **Laravel Cashier 16.7**. El instituto ya tiene credenciales de **sandbox de Ecommerce BBVA** —la pasarela de BBVA México, construida sobre Openpay pero **con marca y documentación propias**— y la decisión tomada es **reemplazar Stripe**, conservando el **cobro automático cada periodo**.

**Rama:** `feature/pagos-bbva`.

**La documentación que manda es `https://docs.ecommercebbva.com/`**, no la de `openpay.mx`. Son productos emparentados pero **no idénticos**: lo que Openpay documenta puede no existir en la instancia de BBVA. Cuando haya discrepancia, gana `docs.ecommercebbva.com` y lo que responda el ejecutivo de cuenta.

### Lo ya verificado contra la documentación (no lo vuelvas a investigar)

| Punto | Lo confirmado |
|---|---|
| URL base sandbox | `https://sand-api.ecommercebbva.com/` |
| URL base producción | `https://api.ecommercebbva.com/` |
| Forma de las rutas | `{base}/v1/{MERCHANT_ID}/{recurso}` |
| Autenticación | **HTTP Basic**: usuario = llave privada `sk_…`, **contraseña vacía**. No hay header propio. |
| **Importes** | **Pesos con decimales, hasta dos.** `"amount": 100` significa **cien pesos**, no un peso. |
| Header obligatorio | `X-Forwarded-For` con **la IP del cliente**, «por disposiciones oficiales para la prevención de fraude E-commerce». |
| 3-D Secure | `use_3d_secure`, **TRUE por defecto**. «Todos los comercios nacen por default con autenticación 3d Secure y VPOS». |
| Retorno del banco | El cargo nace en `charge_pending` y la respuesta trae `payment_method.url`, a donde se manda al tarjetahabiente. BBVA regresa a `redirect_url` **con el id de la transacción en la URL**. |
| Confirmar el pago | `GET /v1/{MERCHANT_ID}/charges/{TRANSACTION_ID}` con la llave privada. |
| Estados | `IN_PROGRESS`, `COMPLETED`, `REFUNDED`, `CHARGE_PENDING`, `CANCELLED`, `FAILED` |
| SDK de PHP | `https://github.com/EcommerceBBVA/BBVA-PHP` — **no está en Packagist**. Se instala como repositorio VCS en `composer.json`. Se cambia de ambiente con `Bbva::setProductionMode(true)`, y el tercer argumento de `getInstance()` es la IP del cliente. |

**Dos trampas que esto ya destapa, y que hay que tratar con cuidado:**

1. **El importe.** Stripe cobra en centavos enteros: el código actual hace `(int) round($plan->precio * 100)`. BBVA cobra en pesos. Si ese cálculo sobrevive al cambio, **se cobra cien veces de más**. Es el error más caro posible en toda esta migración.

2. **`X-Forwarded-For` con la IP del cliente.** Nódico corre detrás del proxy de Hostinger, así que `$request->ip()` puede devolver la IP del proxy y no la de la persona. Revisa `TrustProxies` y comprueba de verdad qué IP sale antes de darlo por bueno: una IP equivocada aquí no da error, solo empeora silenciosamente el antifraude.

### Lo que ya está bien y no se toca

Antes de escribir nada, lee estos archivos. La arquitectura actual ya separó lo que importa, y el trabajo consiste en aprovechar esa separación, no en rehacerla:

- **`app/Servicios/Pagos/ActivadorDeMembresia.php`** — traduce «se pagó» en «hay membresía». Tiene `activar`, `renovar`, `suspenderPorImpago` y el fin de suscripción. **Sus firmas no dependen de Stripe.** Esta clase es la costura: Openpay se enchufa aquí y la lógica de negocio no se entera del cambio. No la modifiques salvo que la Fase 0 demuestre que hace falta.
- **`app/Servicios/Pagos/GestorDeMembresias.php`** — apertura de ciclo, libro de horas y bitácora. Intocable.
- **`app/Servicios/Pagos/ConfirmadorDeOrden.php`** y el flujo de caja — el cobro por referencia y en efectivo, que se confirma a mano. Sigue igual (aunque la Fase 0 puede abrir la puerta a automatizarlo; ver abajo).

### Lo que sí cambia

- **`app/Servicios/Pagos/CobroConTarjeta.php`** — hoy es Cashier puro: `createSetupIntent`, `newSubscription`, `stripe()`, `IncompletePayment`.
- **`app/Http/Controllers/StripeWebhookController.php`** y **`app/Models/EventoStripe.php`**.
- **`app/Http/Controllers/Portal/CheckoutController.php`**, **`resources/js/Pages/Portal/Pago.vue`** (con su prop `vistaPrevia`), y la pantalla de pago de la app móvil (`app-movil/src/app/(miembro)/pagar.tsx`).
- **`app/Console/Commands/SincronizarPreciosStripe.php`** y la columna `stripe_price_id` de `planes`.

---

# FASE 0 — Reconocimiento, y un entregable antes de tocar código

**No escribas código en esta fase.** Igual que en la integración del FR07: primero se averigua, se escribe lo averiguado, y se pide visto bueno. Un contrato de pagos mal entendido no da un error bonito: cobra mal.

La tabla de arriba ya está verificada. Lo que sigue es **lo que no aparece en la documentación pública** y sin lo cual esta migración no se puede terminar. Recórrela entera antes de opinar sobre plazos.

### Los tres huecos

**1. Suscripciones recurrentes — el que decide si el proyecto es viable.**

En la referencia de `docs.ecommercebbva.com` **no aparecen endpoints de planes ni de suscripciones**. Lo único que se le parece es el objeto `PaymentPlan`, que es **meses sin intereses** (`payments`, `payments_type`, `deferred_months`) — otra cosa completamente distinta de cobrar una membresía cada mes.

Busca en el resto de la documentación y en el panel de sandbox si existen `/plans` y `/subscriptions`. **Si no los encuentras, no los inventes ni los supongas por analogía con Openpay.** Repórtalo y para: es una pregunta para el ejecutivo de cuenta de BBVA, no para el código.

Hay además una tensión real que conviene entender antes de preguntar: un cobro recurrente ocurre **sin el tarjetahabiente presente**, y este comercio nace con **3-D Secure obligatorio**, que por definición exige que la persona autentique. Las dos cosas no conviven sin una configuración expresa del banco. Lo normal es que el ejecutivo tenga que habilitar cobros recurrentes o transacciones iniciadas por el comercio, y eximirlas de 3DS. La documentación ya avisa de algo parecido para otro caso: «Para utilizar el cargo sin Vpos se debe solicitar autorización con su ejecutivo de cuenta».

**2. Webhooks.** La referencia **no documenta notificaciones**: ni cómo se registran, ni qué eventos hay, ni cómo se verifica que vienen de BBVA. Búscalo. Si no existe, **no improvises una verificación**: el patrón que la propia documentación respalda es **consultar el cargo por su id con la llave privada** (`GET /charges/{id}`) después del regreso del banco, y esa consulta es igual de segura, porque la verdad la da la API y no lo que llegue por la URL. Diseña sobre eso y dilo explícitamente.

**3. Tarjeta guardada.** Para cobrar otra vez sin pedir de nuevo los datos hace falta guardar la tarjeta contra el cliente (`POST /customers` sí existe). Averigua si se pueden guardar tarjetas y cobrarles después, porque de ahí depende cualquier renovación, sea automática o «un toque».

### Lo que hay que preguntarle al ejecutivo de BBVA

Redáctalo como una lista corta y directa, lista para copiar en un correo:

1. ¿El comercio puede hacer **cobros recurrentes / suscripciones** por API? Si sí, ¿con qué endpoints y qué hay que habilitar?
2. ¿Se pueden **guardar tarjetas** y cobrarlas después sin presencia del tarjetahabiente?
3. ¿Existen **notificaciones/webhooks** y cómo se verifica su autenticidad?
4. ¿Cómo se exime de 3-D Secure a un cobro recurrente, si es que se puede?
5. **¿Se puede facturar un pago con tarjeta?** Hoy Nódico le dice al miembro que con tarjeta **no** hay factura —y por eso existe todo el flujo de referencia y caja—, pero eso venía de una limitación de Stripe. Si con BBVA sí se puede, cambia el mensaje en el sitio, en el portal y en la app, y el flujo de caja deja de ser la única vía con factura. **Esto lo decide el instituto con su contabilidad, no el código.**
6. ¿Qué exige exactamente la **revisión para pasar a producción**? El panel pide que el sitio muestre la información de los productos o servicios que se pagan y que tenga certificado SSL válido; conviene confirmar si hay más.

**Entregable:** `docs/PAGOS-BBVA.md` con lo verificado, lo que falta, el diseño propuesto para confirmar pagos, y las preguntas para el banco. Enséñamelo y espera visto bueno antes de la Fase 1.

### Si no hay suscripciones por API

No te quedes esperando. Deja escrito el plan B para que se pueda decidir: **renovación de un toque** — la tarjeta guardada, y el miembro confirma el cobro desde el portal o la app cuando le llega el aviso de vencimiento, que ya existe. No es cobro automático, pero es una pantalla y un botón en vez de volver a capturar la tarjeta, y se puede construir con lo que la API sí documenta.

---

# FASE 1 — Una costura, dos proveedores

Antes de escribir Openpay, conviértelo en algo intercambiable. No por elegancia: porque durante la migración los dos van a convivir unos días, y porque si BBVA falla hay que poder volver sin revertir el repositorio.

- Define **una interfaz** (`App\Servicios\Pagos\Contratos\PasarelaDePagos`) con lo que Nódico necesita de verdad, no con lo que Stripe ofrece: preparar un cobro, suscribir, consultar si hay suscripción en curso, cancelar. Deriva los métodos de cómo se usa hoy `CobroConTarjeta` desde `CheckoutController` y desde la API móvil.
- El código actual pasa a `PasarelaStripe`, implementando esa interfaz, **sin cambiar su comportamiento**.
- Un ajuste (`config/pagos.php`, leído de `.env`) decide cuál se inyecta.
- **`ActivadorDeMembresia` no cambia.** Si sientes que tienes que cambiarlo, la interfaz está mal pensada: vuelve atrás.

Al terminar esta fase, todo tiene que seguir funcionando exactamente igual que antes con Stripe. Compruébalo antes de seguir.

---

# FASE 2 — BBVA: el cobro único

`PasarelaBbva`, empezando por lo más simple: un pago único de un plan.

- Llaves en `.env` (`BBVA_MERCHANT_ID`, `BBVA_LLAVE_PRIVADA`, `BBVA_LLAVE_PUBLICA`, `BBVA_SANDBOX`), documentadas en `.env.example` **con valores de ejemplo, nunca los reales**. La llave privada no llega jamás al navegador ni a la app.
- Cliente de BBVA por cada miembro, guardado como se guardaba `stripe_id`. Columna con **nombre neutro** (`pasarela_cliente_id`), no `bbva_id`: el siguiente cambio de banco te lo va a agradecer.
- **El importe se manda en pesos con dos decimales.** Escribe la conversión en un solo lugar y ponle una prueba. No reutilices el cálculo de centavos de Stripe.
- **`X-Forwarded-For` con la IP real de la persona** en cada llamada. Comprueba qué IP sale de verdad detrás del proxy de Hostinger antes de darlo por hecho.
- **3-D Secure de principio a fin.** El cargo nace en `charge_pending`; se manda a la persona a la `payment_method.url` que devuelve la respuesta; BBVA la regresa a `redirect_url` con el id de la transacción.
- **La página de retorno no decide nada.** Al volver, consulta `GET /charges/{id}` con la llave privada y actúa según lo que diga la API. Lo que llegue por la URL es una pista, no una prueba: es manipulable.
- Prueba qué pasa si la persona **cierra la pestaña a media autenticación** y si **vuelve dos veces con el mismo id**.
- Errores del banco traducidos a español claro y accionable. «Fondos insuficientes» y «tarjeta reportada» no se le dicen igual a alguien, y ninguno de los dos es «Error 3005».

---

# FASE 3 — La renovación

**Esta fase depende del resultado de la Fase 0.** No empieces hasta que esté decidido si hay suscripciones por API o se va con la renovación de un toque.

Aquí está el trabajo que Cashier hacía gratis y que ahora es nuestro. En cualquiera de los dos caminos:

- Sustituye `stripe_price_id` por una columna neutra y reescribe `SincronizarPreciosStripe` como `nodico:sincronizar-planes-pasarela` (o elimínalo, si con BBVA no hay nada que sincronizar).
- **Conserva la protección contra cobros duplicados** que hoy vive en `suscripcionEnCurso()`. Está ahí porque alguien puede pagar dos veces si se le corta la red, y sin eso se le cobra de más. Léela antes de reescribirla.
- La membresía **la activa la confirmación del cobro contra la API**, nunca la pantalla.

**Si hay suscripciones por API:** alta sobre el plan, cancelación, y qué pasa al final del periodo pagado. Si BBVA reintenta un cobro rechazado por su cuenta, **no reintentes tú encima**: documenta quién reintenta qué.

**Si se va con renovación de un toque:** tarjeta guardada, aviso de vencimiento (ya existe) que lleva a una pantalla donde el miembro confirma el cobro, y el cargo se hace contra la tarjeta guardada. Si ese cargo exige 3-D Secure, el flujo es el mismo de la Fase 2.

---

# FASE 4 — Confirmar el pago

Lo único que puede decir que se pagó es **la API de BBVA**, consultada desde el servidor. Ni el navegador, ni la URL de retorno, ni el usuario.

- **Un confirmador único** que recibe un id de transacción, consulta `GET /charges/{id}` y traduce el estado (`COMPLETED`, `FAILED`, `CHARGE_PENDING`…) a las llamadas que ya existen: pagado → `activar` o `renovar`; rechazado → nada, o `suspenderPorImpago` si era una renovación. Esa es la **única** puerta por la que se activa una membresía.
- **Idempotencia, pase lo que pase.** Generaliza `EventoStripe` a `EventoPasarela` (con su migración de datos) y conserva `procesarUnaVez`, indexado por el id de la transacción. La persona puede recargar la página de retorno, y si además hay webhooks los reenvíos son normales. Sin esto, una membresía se activa dos veces o se duplica un ciclo de horas.
- **Un cargo que se queda en `charge_pending`** —alguien abandonó el 3-D Secure— no es un pago. Decide cuánto se espera antes de darlo por muerto y qué ve el miembro mientras tanto.
- **Si la Fase 0 encuentra webhooks**, añádelos como *complemento*: verifica su autenticidad como diga la documentación y, aun así, **vuelve a consultar el cargo por su id antes de activar nada**. Un webhook avisa; la consulta confirma.
- Registra siempre, aunque el evento se ignore. Una confirmación silenciosa es imposible de depurar a las dos de la mañana.

---

# FASE 5 — Las pantallas

- **`Pago.vue`**: cambia Stripe Elements por el flujo de BBVA. Con VPOS, los datos de la tarjeta se capturan **en el formulario del banco**, no en Nódico: la pantalla prepara el cargo, manda a la `payment_method.url` y recibe de vuelta. **Conserva el comportamiento de `vistaPrevia`**: sin llaves configuradas, la opción de tarjeta se ve deshabilitada *con su motivo*, no desaparece ni truena.
- **Una pantalla de «confirmando tu pago»** al regresar del banco, que consulta el cargo hasta tener respuesta. Ya existe algo así para el webhook de Stripe: reutilízalo.
- **App móvil** (`app-movil/`): la hoja de pago de Stripe (`@stripe/stripe-react-native`) ya no sirve. Como BBVA cobra por redirección a su formulario, lo natural es abrirlo en una vista web dentro de la app y volver por el esquema `nodico://`. **Documenta lo que elijas y por qué**, y corrige la tabla de `docs/APP-MOVIL.md`, que hoy afirma que el pago con tarjeta funciona en Expo Go gracias al SDK de Stripe. Esa fila deja de ser cierta.
- Revisa **todos** los textos que digan «Stripe» o que afirmen que con tarjeta no hay factura. Ese segundo mensaje depende de la pregunta 5 al ejecutivo de BBVA.
- **El sitio tiene que mostrar qué se está pagando.** BBVA revisa el comercio antes de autorizar producción y pide que se vea la información de los productos o servicios y que haya SSL válido. Las páginas de membresías ya lo cumplen; compruébalo antes de solicitar el acceso.

---

# FASE 6 — Quitar Stripe

**Esta fase va al final, y solo cuando BBVA esté cobrando de verdad en el servidor de pruebas, incluida una renovación.** Hasta entonces, Cashier se queda instalado aunque no se use: es la red de seguridad si el banco se atora.

- Quitar `laravel/cashier`, el trait `Billable` y las tablas de Cashier.
- Migración de las columnas `stripe_*`.
- **Antes de borrar nada, dime cuántas filas reales hay** en las tablas de Cashier y en `suscripciones` con datos de Stripe. Si hay membresías de pago reales, esto deja de ser una limpieza y se convierte en una migración de datos que hay que planear aparte.

---

# FASE 7 — Pruebas

- **Una prueba que compruebe el importe exacto** que se manda a BBVA para cada plan, en pesos. Es el error más caro posible y el más fácil de cometer: si se cuela el `*100` de Stripe, se cobra cien veces de más.
- Pruebas automáticas del confirmador: transacción repetida → se procesa una sola vez; `FAILED` → no activa nada; `CHARGE_PENDING` → no activa nada; id inventado → se rechaza sin tocar la membresía.
- **Una prueba de que la página de retorno no activa nada por sí sola.** Llamarla con un id ajeno o inventado no debe activar ninguna membresía.
- Manualmente, en sandbox: pago que aprueba, pago que rechaza, 3-D Secure completo, 3-D Secure abandonado a media autenticación, y cerrar la pestaña justo después de pagar (al volver a entrar, la membresía debe estar activa igual).
- Actualiza `docs/PAGOS.md` con las tarjetas de prueba de BBVA. Las tres de Stripe que están ahí dejan de servir.
- Actualiza `docs/DEMOSTRACION.md` y los dos artefactos de pruebas si cambia el flujo de demostración.

---

## FORMA DE TRABAJAR

- **La Fase 0 primero y sola.** Enséñame `docs/PAGOS-BBVA.md` y espera visto bueno antes de escribir código. Las preguntas al banco salen de ahí y tardan días en responderse: cuanto antes se manden, mejor.
- Después, fase por fase, con el sitio funcionando al final de cada una.
- **No inventes detalles de la API de BBVA, y no los deduzcas de la documentación de Openpay.** Son productos emparentados pero distintos. Si `docs.ecommercebbva.com` no lo dice, dilo y pregunta. Una suposición en un cobro se paga con dinero de alguien.
- **Las llaves privadas nunca se comprometen al repositorio**, ni siquiera las de sandbox, ni siquiera en un ejemplo. En `.env.example` van valores inventados.
- Todo en español, con acentos correctos, y con el porqué escrito donde la decisión no sea obvia — como está el resto del proyecto.
