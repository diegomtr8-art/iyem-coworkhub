# Pagos — Stripe dentro de Nódico

> Fase 4.A. El cobro ocurre **dentro** del sitio con Stripe Elements: el campo de
> tarjeta es un iframe de Stripe montado en la propia página de Nódico. El
> número, el CVC y la fecha **nunca tocan el servidor ni la base de datos**, así
> que Nódico queda en el alcance PCI-DSS más bajo posible.

## Cómo funciona, en una frase

La página cobra; **la verdad la dice el webhook**. La membresía se activa y el
ciclo de horas se abre al recibir el webhook de Stripe, nunca en la página de
retorno (la persona puede pagar y cerrar la pestaña antes de volver).

## Los dos modelos de cobro

| Plan | Cobro | Precio |
|---|---|---|
| Nodo Pro | Suscripción recurrente (se renueva sola) | $599/mes |
| Nodo Match | Suscripción recurrente | $799/mes |
| Nódico Flex | Pago único | $249 |
| Day-Pass | Pago único | $79 |

`cobro_recurrente` en `planes` decide cuál es cuál — el `tipo` no basta, porque
Nódico Flex es mensual pero de pago único.

## Configuración (claves)

En `.env` (de PRUEBA en desarrollo/staging; las de producción solo en el
servidor de producción):

```
STRIPE_KEY=pk_test_...          # publishable — la única que llega al navegador
STRIPE_SECRET=sk_test_...       # secreta — solo en el servidor
STRIPE_WEBHOOK_SECRET=whsec_... # firma del webhook — solo en el servidor
CASHIER_CURRENCY=mxn
```

Sin `STRIPE_KEY` el sitio no ofrece el cobro en línea y cae al enlace de pago de
respaldo (`stripe_url`) de cada plan.

## Dar de alta los precios en Stripe

Cada plan necesita su precio de Stripe en `planes.stripe_price_id`:

1. En el panel de Stripe (modo prueba) crea un **producto** por plan.
2. Para Nodo Pro y Match, un **precio recurrente mensual**; para Day-Pass y
   Flex, un **precio único**.
3. Copia cada `price_...` a la columna `stripe_price_id` del plan
   correspondiente (por `nombre`).

## Registrar el webhook

1. En Stripe → Developers → Webhooks → Add endpoint:
   `https://TU-DOMINIO/stripe/webhook`
2. Eventos a escuchar: `customer.subscription.created`,
   `customer.subscription.deleted`, `invoice.payment_succeeded`,
   `invoice.payment_failed`, `payment_intent.succeeded`.
3. Copia el **signing secret** (`whsec_...`) a `STRIPE_WEBHOOK_SECRET`.

Sin ese secret configurado, el endpoint **rechaza** todo (un webhook sin
verificación de firma es una puerta abierta para activar membresías). En local,
para probar sin dominio público, usa el Stripe CLI:

```
stripe listen --forward-to localhost/stripe/webhook
```

## Tarjetas de prueba

Recorren el flujo completo sin dinero real (cualquier fecha futura y cualquier
CVC):

| Número | Qué hace |
|---|---|
| `4242 4242 4242 4242` | **Aprueba** sin fricción. |
| `4000 0000 0000 0002` | **Rechaza** (tarjeta declinada) → prueba el `invoice.payment_failed`. |
| `4000 0025 0000 3155` | Pide **autenticación del banco** (3-D Secure) → prueba el reto de Cashier. |

## Del modo prueba a producción

1. Cambia las tres claves de `.env` por las **de producción** (`pk_live_…`,
   `sk_live_…`, y el `whsec_…` del endpoint de producción).
2. Crea los productos y precios en el Stripe **de producción** y actualiza los
   `stripe_price_id` de los planes.
3. Registra el webhook contra el dominio de producción.
4. Solo cuando el flujo esté probado en staging, retira los `stripe_url` de
   respaldo de los planes.

## Qué queda por probar de punta a punta

El webhook —lo crítico— está cubierto por pruebas automáticas
(`tests/Feature/Pagos/WebhookStripeTest.php`): activación solo por webhook,
idempotencia, verificación de firma e impago. El **flujo de tarjeta en el
navegador** (Elements) necesita claves de Stripe reales para probarse en vivo;
con las claves de prueba puestas, se recorre entero con las tarjetas de arriba.
