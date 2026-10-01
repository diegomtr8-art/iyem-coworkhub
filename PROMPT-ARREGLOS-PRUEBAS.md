# Prompt para Claude Code — Arreglar lo que encontraron las pruebas de servicio social

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Cinco prestadores de servicio social recorrieron las 31 pruebas de la guía sobre `prueba.nodico.com.mx` y entregaron cuatro reportes, más una tanda automatizada con Playwright. Salieron **23 de 31 pruebas sin incidencias** y un puñado de fallas reales.

**Rama:** `feature/arreglos-pruebas`.

**Dos reglas para todo este trabajo:**

1. **Reproduce antes de arreglar.** Varios hallazgos vienen de una sola persona, uno tiene reportes contradictorios y otro puede ser solo presentación. Arreglar a ciegas lo que no entendiste deja un parche encima de un problema que sigue ahí.
2. **Cada arreglo necesita una prueba automatizada que falle antes y pase después.** Si no se puede escribir esa prueba, dilo: probablemente no entendiste la causa.

---

# FASE 0 — El bloqueo, y no lo adivines

**Nadie puede reservar una sala.** Se elige el espacio, pero el calendario nunca deja seleccionar un día. Lo reportaron los cuatro equipos; uno probó varias redes y datos móviles para descartar conexión, y otro probó los cuatro tipos de espacio. Arrastra consigo las pruebas 13 y 14, que no se pudieron ejecutar. El mismo síntoma aparece al **pedir una asesoría**.

`Reservar.vue` pide los días a `GET /portal/disponibilidad` y, si la respuesta no es `ok`, deja `diasDelMes` vacío — sin decir nada. De ahí el calendario muerto.

## Primero: qué responde de verdad

Contra el servidor de pruebas, con `pro.demo@nodico.com.mx`, mira el **código de respuesta** de esa petición. Cada uno lleva a un sitio distinto:

| Respuesta | Qué significa |
|---|---|
| **403** | El plan no incluye la bolsa de ese espacio (`BolsaDeHoras::incluidaEn`), o no hay suscripción vigente. |
| **404** | El espacio no es reservable por miembro (`Espacio::esReservablePorMiembro`). |
| **200 con `dias: []`** | El rango salió vacío. Ver abajo. |

## La hipótesis principal, y es de datos

Mira `ServicioDeReservas::ultimoDiaReservable()`:

```php
return Reserva::hoy()
    ->addDays((int) config('nodico.operacion.antelacion_maxima_dias', 90))
    ->min(CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), ...));
```

El último día reservable **está topado por la fecha de fin de la membresía**. Y en el controlador, el rango del mes se arma así:

```php
$dia->startOfMonth()->max(Reserva::hoy()),
$dia->endOfMonth()->min($this->reservas->ultimoDiaReservable($suscripcion)),
```

**Si la membresía de la cuenta demo ya venció, el inicio queda después del fin y el periodo sale vacío.** Calendario muerto, sin una palabra de explicación. Encaja exactamente con lo que se vio.

Y hay un motivo para sospecharlo: **el despliegue siembra `NodicoWebSeeder`, no `DemoSeeder`** (míralo en `deploy.sh`). Las suscripciones de las cuentas demo pueden llevar semanas vencidas en el servidor de pruebas.

**Comprueba en el servidor de pruebas** qué suscripción tiene `pro.demo` y con qué `fecha_fin`, y dime qué encontraste antes de cambiar nada.

## Los dos arreglos, que son distintos

**El de datos:** dejar las cuentas demo utilizables en el servidor de pruebas, y que **sigan siéndolo** — si las fechas se siembran fijas, dentro de un mes esto vuelve a pasar y nadie va a acordarse de por qué. Las fechas de demostración deben ser relativas al día en que se siembra.

**El de código, que es el que de verdad importa:** hoy, un miembro cuya membresía está por vencer ve **un calendario sin días y ninguna explicación**. Eso no es un problema de datos de prueba: le va a pasar a cada miembro real cuando se acerque su vencimiento. La pantalla tiene que decir qué ocurre —«tu membresía vence el 14 de octubre, no puedes reservar después de esa fecha», o «tu membresía venció, renuévala para reservar»— con su enlace a renovar. Lo mismo cuando la respuesta sea 403 o 404: **un fallo silencioso no es aceptable en la pantalla más usada del portal**.

Cuando esto quede, **vuelve a probar la asesoría antes de investigarla por separado**: es el mismo síntoma y probablemente la misma causa.

---

# FASE 1 — Las graves

## 1.1 El formulario de contacto no confirma nada

Se envía, la página se recarga y no aparece confirmación. Tampoco llegó correo.

El servidor **sí** manda el aviso (`back()->with('contacto_ok', true)`), `HandleInertiaRequests` lo comparte y `ContactSection.vue` lo lee. Así que lo más probable es que **el mensaje sí se pinte pero fuera de la vista**: el formulario está al final del inicio y al volver la página regresa arriba. Compruébalo antes de tocar la lógica, y si es eso, devuelve la vista al formulario tras enviar.

**El correo es un asunto aparte.** El envío está envuelto en un `try/catch` que **solo escribe en el log**, así que si falla nadie se entera. El correo de registro sí llegó en menos de dos minutos, o sea que el SMTP funciona: revisa **a qué buzón** se está mandando (`ContenidoDelSitio::valor('contacto','email')`) y si ese buzón existe de verdad. Busca en `storage/logs/laravel.log` la línea «No se pudo enviar el correo de contacto». Un fallo de correo tiene que verse en el panel, no solo en un log que nadie abre.

## 1.2 Recepción no puede mover una reserva

> «No pude crear la reserva de horario, me pedía un user id el cual no se muestra en ninguna parte de la página.»

Recepción trabaja con nombres, no con identificadores de base de datos. Pon un buscador por nombre o correo — ya existe `Components/Panel/BuscadorMiembro.vue`. **Ningún identificador interno debería aparecer en un formulario de recepción.**

## 1.3 El check-in registra cero minutos

Entrada, diez segundos, salida: sale «0 minutos». Y tras el segundo registro la pantalla no dejó añadir más personas hasta recargar.

Diez segundos **sí** redondean a cero, así que puede ser solo presentación. Pero **este proyecto ya tuvo un error de cálculo de tiempo con Carbon 3**, donde `diffInMinutes` devolvía negativos e invertía la contabilidad de horas (BUG-01). Repite la prueba con una permanencia de varios minutos antes de descartarlo, y revisa cómo se calcula `duracion_minutos`. Si el cálculo está bien, enseña segundos o «menos de un minuto» en lugar de un cero que parece un error.

**La lista que deja de aceptar personas hasta recargar es un fallo distinto**: la pantalla no se refresca después de registrar. Arréglalo aparte.

## 1.4 Ajustar horas: reportes contradictorios

Un probador dice que el modal se queda abierto y no guarda; otro dice que funciona bien. **No lo arregles a ciegas.**

La diferencia más probable es el motivo del ajuste, que es obligatorio: si se manda vacío, la validación falla y el modal se queda abierto. Si es eso, **el fallo real es que el error de validación no se está enseñando** y la persona no sabe por qué no pasa nada. Reprodúcelo primero y dime qué encontraste.

---

# FASE 2 — Las medias y el pulido

Estas pueden ir juntas en una sola tanda, pero cada una con su prueba.

- **La tarjeta con 3-D Secure sale rechazada.** Con `4000 0025 0000 3155` el cobro se marca como rechazado sin mostrar la autenticación del banco. Si el flujo trata el estado «requiere acción» como un rechazo, **en producción se van a rechazar pagos buenos** de cualquiera cuyo banco pida autenticación, que en México es la mayoría. Revísalo ahora y vuelve a revisarlo cuando se decida la pasarela de BBVA, donde 3-D Secure viene activo por omisión.

- **El carrusel de membresías necesita dos clics.** Al llegar al extremo, el botón de ese lado sigue admitiendo pulsaciones y se pone transparente; después el del otro lado no responde la primera vez. El estado visual de «deshabilitado» y la posición real del carrusel están desincronizados. En `PlanesCarousel.vue`.

- **El video del inicio no carga en iPhone.** Safari en iOS no reproduce solo un video sin `muted` y `playsinline`. Revisa esos dos atributos en `HeroVideo.vue` antes de buscar más lejos.

- **El texto alrededor del sello «$0» no se lee.** Dos probadores lo señalaron por separado: el texto circular se mezcla con el fondo. En `SelloGiratorio.vue`.

- **Varios títulos se ven pequeños y claros** en escritorio: «Servicios», «Membresías», «Registro», «Con el respaldo de», «Instagram». Relacionado y probablemente parte de la causa: **en `Welcome.vue` esos encabezados se pintan sin título** — a `SectionHeading` solo se le pasa `etiqueta`, así que el `<h2>` queda vacío. En `Nosotros.vue` sí se pasa `titulo`. Eso también afecta a buscadores y lectores de pantalla. Arréglalo, y de paso revisa el contraste.

- **Las ventanas se cierran al soltar el clic fuera.** Si se selecciona texto dentro de un campo y se suelta el botón fuera del modal, la ventana se cierra y se pierde lo escrito. El cierre debe exigir que **el pulsar y el soltar** ocurran ambos fuera.

- **Un rectángulo flotante sobre el texto del inicio en celular**, que desaparece al tocar otro punto de la pantalla. Reprodúcelo en un móvil real.

- **Tras confirmar el correo de registro, «los datos quedaron raros»** en la pantalla de inicio de sesión. Hay una captura en el reporte de Jonathan Hillman y Daniel Sandoval; míralo ahí, porque la descripción sola no alcanza para reproducirlo.

- **La página de acceso denegado.** Cuando recepción intenta entrar a Reportes recibe un **404 pelón sin estilo**. Que no alcance Reportes está bien, y devolver 404 en vez de 403 es defendible porque no revela qué existe. Lo que no está bien es que sea la página de error por defecto: tiene que ser una de Nódico que explique qué pasó y a dónde ir.

---

# FASE 3 — Limpiar la suite automatizada

La tanda de Playwright marcó **cuatro fallos que no son defectos del sitio**. Una suite que grita en falso deja de leerse, así que esto se arregla antes de volver a correrla:

- **«El registro da 404».** La ruta existe (`routes/auth.php`) y dos probadores humanos se registraron con éxito. La prueba pide una dirección equivocada.
- **`wizard-zoom.spec.ts` apunta a `127.0.0.1:8000`** y valida un flujo de CURP que Nódico no tiene. **Es de otro proyecto.** Sácalo.
- **`ERR_NAME_NOT_RESOLVED` al cancelar una reserva**: fallo de DNS de la máquina que corrió la prueba, no del sitio.
- **Cuatro pruebas que expiraron a los 45 segundos** (datos fiscales, asesoría, membresía por vencer, emprendedor de la semana). En las capturas el portal aparece cargado y con el menú completo, y los probadores humanos marcaron esas mismas pruebas como correctas. Son selectores que no encuentran su elemento: **arregla la prueba, no la página.**

Y para que la siguiente tanda no se quede a medias, **siembra los datos que faltaron**: varias órdenes de pago pendientes (la prueba 27 no se pudo hacer porque la única la consumió la 26) y facturas por emitir (la prueba 28 no tenía ninguna).

---

## UNA DECISIÓN QUE NO ES TUYA

**No existe el botón de «Entrar con Google»** ni en iniciar sesión ni en registrarse. Tres fuentes lo reportaron. El servidor ya acepta `POST /auth/google`, así que falta la parte visible.

**Pregúntame antes de hacer nada**: o se construye el botón, o se quita la prueba 08 de la guía. Las dos son válidas; dejarlo así hace que cada tanda de pruebas reporte el mismo hallazgo.

---

## FORMA DE TRABAJAR

- **La Fase 0 primero y sola.** Enséñame qué responde `/portal/disponibilidad` y qué encontraste en las suscripciones demo **antes** de cambiar una línea.
- Después, fase por fase, con el sitio funcionando al final de cada una.
- **Los hallazgos de un solo reporte se reproducen antes de arreglarse.** Si no lo consigues, dilo en vez de parchear a ciegas.
- Distingue siempre **problema de datos** de **problema de código**. Varios de estos son lo primero disfrazado de lo segundo, y el arreglo correcto es distinto.
- Todo en español, con acentos correctos, y con el porqué escrito donde la decisión no sea obvia — como está el resto del proyecto.
