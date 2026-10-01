# Prompt para Claude Code — Acercar Nódico a producción

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## DECISIONES YA TOMADAS

No las replantees; actúa sobre ellas.

| Asunto | Decisión |
|---|---|
| Dominio final | `nodico.com.mx`, pero **todavía no**. Se sigue trabajando y desplegando en `prueba.nodico.com.mx` hasta nuevo aviso. |
| Pasarela de cobro | La de **BBVA / Openpay**. Stripe sale de servicio. (Ojo con cuál de las dos: ver Fase 2.) |
| Textos legales | Se escriben **borradores de verdad** ahora; jurídico los revisa después. Siguen marcados como provisionales. |
| Ingreso con Google | **Se construye**, y queda listo esperando credenciales. |

**Rama:** trabaja sobre `feature/arreglos-pruebas`, que es donde está todo.

---

# FASE 1 — Integrar los 110 commits a `main`

Todo el trabajo vive en ramas y `main` lleva meses atrás. `main` **no tiene ni un commit** que las ramas no tengan, así que es un avance rápido sin conflictos posibles.

Ya existe `integrar-a-main.bat` para esto, pero **apunta a la rama equivocada**: dice `feature/reconexion-terminal`, que es de hace semanas. Cámbialo a la rama actual antes de usarlo, y comprueba que sigue siendo avance rápido (el propio script se niega si no lo es; no lo fuerces).

Antes de integrar, **enséñame `git status`**: si queda algo sin confirmar, decidimos qué entra y qué no.

Después de integrar, sube `main` y confirma que el remoto quedó al día.

---

# FASE 2 — Cambiar a la pasarela del banco

## Primero, una comprobación que vale un día de trabajo

`config/pagos.php` tiene **tres** valores posibles y dos de ellos son del banco, pero no son lo mismo:

- **`openpay`** → apunta a `sandbox-api.openpay.mx`, captura por token, antifraude de Openpay.
- **`bbva`** → apunta a `sand-api.ecommercebbva.com`, captura VPOS, número de afiliación.

Las credenciales de sandbox que tiene el instituto son de **Ecommerce BBVA** (la URL que entregó el panel es `https://sand-api.ecommercebbva.com/v1/`). Si es así, el valor correcto es **`bbva`**, no `openpay`, aunque coloquialmente se le diga Openpay a todo.

**Comprueba con qué credenciales está configurado el servidor de pruebas y dime cuál de las dos corresponde antes de cambiar nada.** Poner el valor equivocado da errores de autenticación que parecen de credenciales y no lo son.

## Después

- Cambia `PAGOS_PASARELA` al valor correcto en el `.env` del servidor de pruebas y en `.env.example`.
- **Comprueba el cobro completo de punta a punta** en el sandbox: pago aprobado, pago rechazado, 3-D Secure terminado, 3-D Secure abandonado a media autenticación, y la renovación recurrente.
- **Vuelve a probar la tarjeta que tus probadores reportaron** (`4000 0025 0000 3155` en Stripe; usa la equivalente del sandbox del banco). El hallazgo era que una tarjeta que pide autenticación se trataba como rechazo. Ese es exactamente el fallo que no puede llegar a producción, porque en México la mayoría de los bancos piden autenticación.
- Revisa que **todos los textos del sitio** digan la pasarela correcta. Ya hubo un arreglo para eso (`9f6b5a1`); confirma que no quedó ningún «Stripe» suelto en el portal, la app o los correos.
- **No borres Stripe todavía.** Que quede inactivo pero instalado hasta que el cobro del banco lleve unos días funcionando en pruebas.

---

# FASE 3 — Textos legales en borrador

`resources/legal/aviso-de-privacidad.md` y `terminos.md` están en versión `1.0-provisional`.

Escribe **borradores completos y utilizables**, no un esqueleto: el aviso de privacidad con responsable, datos que se recaban, finalidades, transferencias, derechos ARCO y cómo ejercerlos; los términos con objeto, membresías y vigencias, reglas de uso del espacio, reservas y cancelaciones, pagos y facturación, suspensión y terminación.

Tres reglas:

1. **Que digan la verdad sobre lo que el sistema hace de verdad.** Nódico guarda datos fiscales, fotos de perfil, registros de acceso con reconocimiento facial y bitácora de movimientos. Un aviso de privacidad que no mencione el dato biométrico del torno es un aviso incorrecto, y eso es precisamente lo que jurídico va a buscar.
2. **Se quedan marcados `provisional: true`**, con el cartel visible en el sitio. No los des por definitivos.
3. **Deja una lista de las decisiones que jurídico tiene que tomar** al final de un documento aparte (`docs/LEGAL-PENDIENTES.md`): plazos de conservación, si hay transferencias a terceros, quién es el responsable formal ante el INAI, y la dirección de contacto para derechos ARCO. No las inventes: márcalas.

---

# FASE 4 — Los siete hallazgos abiertos

Cada uno con su prueba automatizada que falle antes y pase después.

1. **El carrusel de membresías necesita dos clics.** Los botones se apagan visualmente pero siguen contando pulsaciones: el estado de «deshabilitado» y la posición real se desincronizan. En `PlanesCarousel.vue`.
2. **El texto del sello «$0» no se lee.** Dos probadores por separado. Contraste insuficiente del texto circular contra el fondo, en `SelloGiratorio.vue`.
3. **Las ventanas se cierran al soltar el clic fuera.** Si se selecciona texto dentro de un campo y se suelta el botón fuera del modal, se cierra y se pierde lo escrito. Debe exigir que **pulsar y soltar ocurran ambos fuera**. Revisa todos los modales, no solo el que se reportó.
4. **Falta la pantalla de 404.** Existe `Pages/Errors/403.vue` pero no la de 404, y el caso que vieron —recepción intentando entrar a Reportes— devuelve 404, así que sale la página por defecto de Laravel. Hazla con el diseño de Nódico y que explique qué pasó y a dónde ir.
5. **Un rectángulo flotante sobre el texto del inicio, en celular**, que desaparece al tocar otro punto. Ver Fase 5.
6. **«Los datos quedaron raros» tras confirmar el correo de registro.** Ver Fase 5.
7. **El ingreso con Google.** Ver Fase 6.

---

# FASE 5 — Los tres que quedaron en duda

**Reprodúcelos antes de tocarlos.** Si no lo consigues, dilo en vez de parchear a ciegas.

- **Ajustar horas a un miembro.** Un probador dice que el modal se queda abierto y no guarda; otro dice que funciona. La sospecha: el motivo es obligatorio, se envía vacío, la validación falla y **el error no se enseña**. Si es eso, el arreglo es enseñar el error, no cambiar la lógica.
- **El video del inicio en iPhone.** El reproductor **ya trae** `playsinline` y silenciado, así que la causa es otra. Necesita un iPhone real; si no tienes uno, dilo y lo probamos con el equipo.
- **El rectángulo flotante en celular.** Igual: móvil real.

---

# FASE 6 — Dejar listo el ingreso con Google

El servidor ya acepta el ingreso con Google (`POST /auth/google`), existe `config/services.php → google` y hay un interruptor `NODICO_GOOGLE_LOGIN_ENABLED` que está en `false`. Falta la parte visible y las credenciales.

Construye **todo menos las credenciales**:

- El botón en iniciar sesión y en registrarse, con el diseño de Nódico y respetando las reglas de marca de Google para el botón.
- El flujo completo de ida y vuelta, el manejo del error cuando la persona cancela, y el caso de un correo que ya existe con contraseña.
- **Que el botón solo aparezca si el interruptor está encendido y hay credenciales.** Sin ellas, que no se vea — no un botón que truena al pulsarlo.
- Déjalo apagado y documenta en `docs/AUTH-PROVEEDORES.md` qué hay que poner para encenderlo.

**No puedes crear las credenciales tú**: salen de la consola de Google Cloud y las tiene que generar el instituto. Escribe en ese documento, paso a paso, qué hay que pedirle a quien tenga la cuenta.

---

# FASE 7 — Preparar producción sin encenderla

**No cambies el dominio ni despliegues a producción.** Todo sigue en `prueba.nodico.com.mx` hasta nuevo aviso. Lo que sí se puede adelantar es dejar el procedimiento listo y probado:

- Un documento `docs/PRODUCCION.md` con **la lista exacta de pasos** del día del lanzamiento, en orden, cada uno con su comprobación. Que no haya que improvisar nada ese día.
- La plantilla del `.env` de producción, **con los valores que ya se conocen puestos** y los que faltan marcados con claridad.
- **Qué hace falta de fuera**, listado aparte y sin rodeos: credenciales del banco para producción, buzón de correo del dominio propio, acceso al panel de Hostinger y el cambio de raíz del documento.
- **Comprueba que el comando de limpieza de datos demo no corre en producción.** Ya hay una prueba de eso; confirma que sigue pasando.

---

## FORMA DE TRABAJAR

- **Fase 1 primero y sola**, y enséñame `git status` antes de integrar.
- **La comprobación de la Fase 2 antes de cambiar la pasarela.** Dime qué credenciales encontraste y cuál de los dos valores corresponde.
- Después, fase por fase, con el sitio funcionando al final de cada una.
- **Lo que no se pueda reproducir, no se arregla**: se reporta.
- No borres Stripe ni cambies el dominio en este encargo. Los dos tienen su momento y no es éste.
- Todo en español, con acentos correctos, y con el porqué escrito donde la decisión no sea obvia.
