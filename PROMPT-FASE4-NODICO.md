# Prompt para Claude Code — Fase 4: cobro propio, catálogos, datos de prueba y responsive total

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida. El sitio público, la autenticación y los dos portales ya están construidos y corriendo en `prueba.nodico.com.mx`.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4 · Vite 7 · Carbon 3.11. Sistema de diseño «editorial técnico» en `.claude/skills/nodico-design/SKILL.md`.

### ⛔ Antes de escribir una línea de código

`feature/auth` y `feature/portales` se separaron en el commit `6bb34af`. Auth tiene 7 commits que portales no tiene —incluido **un arreglo de seguridad** (el identificador de sesión viajaba en la URL) y **toda la pantalla de consentimiento legal**— y portales tiene 13 que auth no tiene. Las dos tocaron los mismos cinco archivos: `User.php`, `routes/web.php`, `HandleInertiaRequests.php` y los dos layouts.

**Fusiona las dos ramas primero**, sube el resultado a `main` y a `origin/main`, y despliega. Hasta que eso pase, el arreglo de seguridad y el consentimiento legal no existen para nadie. Después abre `feature/fase4` desde ahí.

### Decisiones ya tomadas — no las replantees

| Tema | Decisión |
|---|---|
| Cobro | **Suscripción con renovación automática** para Nodo Pro y Nodo Match. Day-Pass y Nódico Flex, pago único. El miembro puede cancelar desde su portal. |
| Instagram | La cuenta **@nodicomx es de empresa y está vinculada a una página de Facebook**, así que se usa la API oficial de Meta. |
| Day-pass gratuito | **Recepción lo registra en el momento**, sin solicitud previa en línea. |

**Al empezar:** revisa qué skills tienes disponibles y usa las que apliquen. Al terminar, corre `/security-review` sobre el diff completo.

---

# FASE A — Cobrar dentro de Nódico

Hoy los botones de membresía mandan a un enlace de pago de Stripe: la persona sale del sitio, paga en una página con la marca de Stripe y regresa —si regresa— sin que Nódico se entere de nada. La membresía se activa a mano.

Instala **Laravel Cashier** y móntalo así:

### A.1 · El pago ocurre dentro del sitio, la tarjeta nunca la toca Nódico

Usa **Stripe Elements**. El campo de tarjeta es un iframe servido por Stripe dentro de tu propia página: visualmente el pago pasa en Nódico, pero los datos de la tarjeta nunca tocan el servidor ni la base de datos.

**Esto no es opcional ni negociable:** no captures número de tarjeta, CVC ni fecha en campos propios, ni los envíes a tu backend «solo de paso». Eso convierte al proyecto en sujeto obligado de PCI-DSS y es un riesgo que el IYEM no debe correr. Con Elements, Nódico queda en el alcance más bajo posible.

### A.2 · Dos modelos de cobro según el plan

- **Nodo Pro ($599/mes) y Nodo Match ($799/mes):** suscripción recurrente. Se renuevan solas. El miembro puede cancelar y sigue con acceso hasta el fin del periodo pagado.
- **Day-Pass ($79) y Nódico Flex ($249):** cobro único, sin renovación.

El plan ya trae el precio en la base de datos. Crea los precios correspondientes en Stripe y guarda su identificador en la tabla `planes` — no dupliques importes a mano en el código.

### A.3 · La verdad la dice el webhook, no el navegador

Esto es lo que más se hace mal. La persona puede pagar y cerrar la pestaña antes de volver: si activas la membresía en la página de retorno, esa persona pagó y no tiene nada.

- La activación de la membresía, la creación de la `Suscripcion` y la apertura del ciclo en el libro de horas ocurren **al recibir el webhook de Stripe**, no en el redireccionamiento.
- Verifica la firma de cada webhook. Un endpoint de webhook sin verificación de firma es una puerta abierta para que cualquiera active membresías.
- Hazlo **idempotente**: Stripe reintenta, y el mismo evento puede llegar varias veces. Guarda el identificador del evento y descarta repetidos. Sin esto, un reintento abre dos ciclos y regala el doble de horas.
- Atiende también el pago fallido y la cancelación: suspender la membresía, avisar al miembro y dejarlo en la bandeja del panel operativo.
- La página de retorno solo muestra «estamos confirmando tu pago» y consulta el estado. Nunca decide.

### A.4 · Desde el portal del miembro

Contratar, ver el método de pago, cambiarlo, ver los cobros, cancelar la renovación y reactivarla. Al cambiar de plan, define y documenta qué pasa con las bolsas de horas del ciclo en curso.

### A.5 · Modo de pruebas

Deja el sistema funcionando con las claves de prueba de Stripe y documenta en `docs/PAGOS.md` las tarjetas de prueba —la que aprueba, la que rechaza y la que pide autenticación del banco— para que se pueda recorrer el flujo completo sin dinero real. Documenta también cómo se pasa a claves de producción y cómo se registra el webhook en el panel de Stripe.

Los enlaces de pago actuales (`stripe_url`) quedan como respaldo hasta que el flujo nuevo esté probado en staging. Después se retiran.

---

# FASE B — Espacios reales

El seeder tiene **5 cubículos privados y 1 sala de juntas**. Los espacios reales de Nódico son:

| Espacio | Cantidad | Tipo |
|---|---|---|
| Cubículo privado | **4** | `privado` |
| Sala de juntas | **2** | `sala_juntas` |
| Sala de creación de contenido / Podcast | 1 | `contenido` |
| Sala de fotografía | 1 | `fotografia` |
| Área de coworking | 1 | `coworking` |
| Salón para eventos (Yucatán Emprende 1 y 2) | 2 | `salon_eventos` |

Corrige el seeder **y** escribe una migración que ajuste los datos existentes sin borrar reservas: si hay reservas colgando del cubículo que sobra, muévelas o avisa en vez de romper la clave foránea.

Dales nombres que recepción reconozca al vuelo, y revisa que las amenidades de cada uno correspondan a la realidad.

---

# FASE C — Seguridad dentro del portal

## C.1 · El bug que reportó Nódico

En `Portal/Perfil.vue`, el botón «Cambiar» junto al correo apunta a `route('profile.edit')`, que es la pantalla de Breeze: saca a la persona del portal y la deja en una página con otro diseño, sin navegación de Nódico. Se ve como si el sitio se hubiera roto.

## C.2 · Lo que hay que hacer

Todo lo de la cuenta vive **dentro del portal que corresponda**, con su layout y su navegación:

- Cambiar contraseña.
- **Cambiar correo**, con el patrón seguro: confirmar la contraseña actual, enviar verificación a la **dirección nueva**, y no aplicar el cambio hasta que se verifique. Avisa por correo a la **dirección anterior** de que se solicitó el cambio — es lo que permite reaccionar si alguien secuestró la sesión.
- Activar y desactivar el segundo factor, con sus códigos de recuperación.
- Sesiones abiertas y cierre remoto.
- Identidades vinculadas (Google), con la regla de que no se desvincula el último método de acceso.
- Bitácora propia de eventos de seguridad.

Lo mismo para el portal operativo, con su propio layout. Las pantallas de Breeze en `/profile` se retiran o redirigen a la ruta del portal que corresponda; que no quede ninguna página huérfana con otro diseño.

---

# FASE D — Asesoría IYEM: temas y colaboradores

Hoy el miembro solicita asesoría escribiendo un tema libre. Falta la oferta.

### D.1 · Catálogo de temas

Tabla propia, administrable desde el panel: nombre, descripción corta, categoría (**básicos** y **especializados**), duración sugerida y si está activo. Siembra una oferta inicial razonable para un instituto de emprendedores —modelo de negocio, finanzas básicas, precios y costos, marca y redes, ventas, aspectos legales y fiscales, propiedad industrial, acceso a financiamiento, comercio electrónico— y **márcala para que el IYEM la valide**: son sus servicios, no los inventes como definitivos.

### D.2 · Catálogo de colaboradores

El modelo `Asesor` ya existe; extiéndelo con foto, especialidades (ligadas a los temas), semblanza breve y disponibilidad general. Administrable desde el panel.

### D.3 · En el portal del miembro

La pantalla de asesoría deja de ser un formulario en blanco y pasa a ser una oferta que se puede explorar: temas agrupados por categoría, con los asesores que los imparten. Se elige tema, opcionalmente asesor preferido, y día y horario preferidos. Se ve cuántas horas de asesoría le quedan.

### D.4 · En el panel operativo

Bandeja de solicitudes con el tema y el asesor sugerido, para confirmar asignando asesor y horario. Vista de carga por asesor. CRUD de temas y de colaboradores.

---

# FASE E — Day-pass gratuito para el interior del estado

Nódico regala el day-pass a emprendedores y artesanos del interior del estado, pero hoy no queda registro de nadie: no hay forma de saber cuántos han venido ni de qué municipios, que es justo lo que el IYEM necesita para justificar el programa.

**Recepción lo registra en el momento**, sin solicitud previa.

- **Alta rápida en el panel**, pensada para el mostrador: nombre, teléfono, municipio del interior, giro o tipo de artesanía, y de dónde se enteró. Que no tome más de un minuto con alguien esperando enfrente.
- Se crea el registro de acceso del día y queda enlazado al check-in, para que la persona aparezca en el tablero como cualquier otro visitante.
- Si vuelve, recepción la encuentra buscando por nombre o teléfono y no vuelve a capturar todo.
- **Límite configurable** —por ejemplo uno al mes por persona— que recepción pueda saltarse dejando el motivo. El sistema avisa, no bloquea.
- **Sección propia en el panel** con el listado, filtros por municipio, giro y periodo, y exportación a Excel para el reporte al IYEM.
- En reportes: cuántos day-passes gratuitos por mes, de qué municipios, qué giros. Ese es el dato que hace que el programa se sostenga.

---

# FASE F — Datos de prueba

Nódico necesita ver el sistema funcionando antes de conectar el Face ID. Crea un **seeder de demostración separado** del de producción.

**Blindaje obligatorio:** que se niegue a correr si `APP_ENV=production`. Un seeder de demostración ejecutado sobre datos reales es un desastre irreversible.

Debe dejar el sistema como si llevara meses operando:

- **Un miembro con Nodo Pro activa** y su historial completo: horas consumidas y disponibles a media bolsa, reservas pasadas y futuras, un no-show, una cancelación devuelta y otra tardía, check-ins, asesorías realizadas y una pendiente, datos fiscales completos y sus cobros.
- Miembros con los otros tres planes, y algunos en estados distintos: pendiente de activación, suspendido, con la membresía por vencer esta semana.
- Un admin y un usuario de recepción.
- Reservas repartidas por los ocho espacios, en la semana pasada y la que viene, con horarios realistas — que la agenda del panel se vea llena, no vacía.
- Day-passes gratuitos de varios municipios del interior.
- Emprendedores del catálogo, temas de asesoría, asesores, eventos, y avisos.

Documenta en `docs/DEMOSTRACION.md` los accesos de cada usuario de prueba y qué se puede ver con cada uno, y el comando para restablecer todo desde cero.

---

# FASE G — Sitio público

### G.1 · El mapa se ve desde el inicio

Hoy es una fachada gris con un botón «Ver el mapa». Nódico quiere ver el mapa directamente.

Cárgalo, pero sin pagar el costo en el primer pintado: crea el iframe cuando la sección de contacto **se acerque** a la pantalla, con `IntersectionObserver`. Como esa sección está al final de todas las páginas, en la práctica el mapa se ve siempre que alguien llega ahí, y nunca compite con la carga inicial. Reserva la altura para que no salte el layout.

### G.2 · La dirección corta

Cambia `direccion_corta` en `config/nodico.php` de «Hacienda Sodzil Nte., Mérida, Yucatán» a **«Instituto Yucateco de Emprendedores»**. Revisa dónde se usa —el riel del hero y la franja de contacto— y confirma que la dirección completa siga apareciendo donde hace falta para llegar físicamente.

### G.3 · Feed real de Instagram

Las cuatro publicaciones sueltas se quedan cortas. Como la cuenta es de empresa y está vinculada a una página de Facebook, usa la **API oficial de Meta** (Instagram Graph API):

- Cabecera con foto de perfil, nombre, número de seguidores y de publicaciones, como el widget que Nódico usó de referencia.
- Reja de 12 publicaciones con su miniatura, marca de video o carrusel, y enlace a la publicación.
- **Guarda la respuesta en caché en el servidor** —una hora está bien— y sirve desde ahí. Nunca llames a la API desde el navegador de cada visitante: expondrías el token.
- **El token de larga duración caduca a los 60 días.** Programa una tarea que lo renueve mucho antes, y que avise si falla. Es el punto donde estos feeds se rompen meses después, sin que nadie se entere hasta que un cliente lo nota.
- Si la API falla o el token murió, cae a la reja curada actual desde la tabla `ajustes`. La sección nunca debe quedar vacía ni mostrar un error de Meta.
- Documenta en `docs/INSTAGRAM.md` cómo se generó el token, cómo se renueva a mano si hace falta, y qué permisos exige.

### G.4 · Herencia Viva

El enlace debe mostrar **`www.herenciaviva.com`** y apuntar a `https://www.herenciaviva.com`.

### G.5 · La sección de salones se ve estrecha

En la portada, el bloque de salones tiene poco aire vertical y la imagen de fondo queda aplastada. Dale más altura y más espacio interior, y comprueba que la foto respire en 1366 px y en 1920 px. Que se sienta como una sección, no como una franja.

### G.6 · Fuera la agenda de Actividades

Quita por completo la sección «Nuestro contenido más reciente» de `/actividades`, con su estado vacío incluido. El calendario de Luma ya cubre esa función y hoy la página abre con un bloque que dice que no hay nada.

---

# FASE H — Emprendedores: catálogo y rotación

Hoy el «Emprendedor de la semana» está fijo en Salabtún y el directorio sale de una tabla sin administración.

### H.1 · Catálogo de emprendimientos

CRUD completo en el panel: nombre, foto, descripción, giro, municipio, Instagram, sitio web, si egresó de algún programa del IYEM, y si está activo. Una sola tabla alimenta las dos cosas.

### H.2 · Directorio

Desde el panel se elige cuáles aparecen en `/actividades` y en qué orden. Con vista previa de cómo queda la reja.

### H.3 · Emprendedor de la semana, rotando solo

- Una tarea programada que **cada semana** pase al siguiente del catálogo, entre los marcados como elegibles.
- La rotación es justa: el que lleva más tiempo sin salir es el próximo, y no se repite hasta que todos hayan pasado.
- **Calendario de rotación visible en el panel**: quién está esta semana, quién sigue las próximas. Que se pueda fijar uno a mano para una semana concreta —cuando haya algo que celebrar— sin romper el ciclo.
- Si el catálogo se queda sin elegibles, mantén al último y avisa en el panel. Nunca dejes la sección vacía.

### H.4 · El «Saber más» que se perdió

La ficha del emprendedor destacado quedó sin destino al migrar desde Odoo. Ahora que hay catálogo, apúntala a su Instagram o a su ficha, según lo que tenga cargado.

---

# FASE I — Responsive de verdad, en todo

Todo Nódico —sitio público, portal del miembro y portal operativo— tiene que funcionar bien en iPhone y iPad. Pruébalo **en el navegador**, ancho por ancho, no leyendo el código:

| Dispositivo | Ancho |
|---|---|
| iPhone SE | 375 px |
| iPhone 15 | 393 px |
| iPhone 15 Pro Max | 430 px |
| iPhone en horizontal | 852 px |
| iPad mini vertical | 744 px |
| iPad Pro vertical | 1024 px |
| iPad Pro horizontal | 1366 px |

**El portal operativo es el difícil**, y es el que más se va a usar en tablet desde el mostrador:

- Las tablas densas no caben en 375 px. Conviértelas en tarjetas apiladas en móvil, no en tablas con scroll horizontal que nadie puede leer.
- La agenda semanal de espacios necesita tratamiento propio: en iPad puede seguir siendo línea de tiempo; en iPhone, lista por día.
- Cabeceras y filtros pegajosos para no perder el contexto al desplazar listas largas.
- El buscador de miembros y el check-in tienen que ser cómodos con una mano.

Reglas para todo el proyecto: área táctil de 44 px mínimo, cero scroll horizontal en cualquier ancho, campos de formulario con al menos 16 px para que iOS no haga zoom al enfocar, y respeto al área segura del notch.

Toma capturas de cada ancho, revísalas tú mismo y corrige antes de enseñármelas.

---

# FASE J — Verificación y despliegue

1. `npm run build` y `php artisan test` sin errores. Añade pruebas para lo nuevo: que el webhook de Stripe sea idempotente y verifique la firma, que la membresía se active solo por webhook, que el cambio de correo exija verificar la dirección nueva, que la rotación semanal no repita hasta agotar el catálogo, que el seeder de demostración se niegue en producción, y que el límite de day-pass gratuito avise sin bloquear.
2. Consola del navegador limpia en todas las páginas, públicas y privadas.
3. Recorrido completo solo con teclado.
4. Lighthouse en móvil sobre la portada: comprueba que el mapa y el feed de Instagram no hayan tirado el rendimiento. Reporta las cifras antes y después.
5. `/security-review` sobre el diff — con pagos de por medio, esta vez es especialmente importante.
6. Despliegue siguiendo `docs/DEPLOY.md`. Recuerda que Hostinger no tiene Node y que hay que sincronizar las dos rutas de `build/`.
7. En el servidor: registrar el webhook de Stripe y comprobar que llega, verificar que la tarea de renovación del token de Instagram está en el cron, y correr el seeder de demostración solo en staging.

---

## FORMA DE TRABAJAR

- Primero la fusión de ramas y el despliegue de lo que ya existe. Eso va solo, en su propio paso, antes de tocar nada nuevo.
- Después muéstrame el plan de la Fase 4 y espera mi visto bueno.
- Orden sugerido: B y C (rápidas y desbloquean pruebas) → F (datos, para poder ver todo) → A (pagos) → D, E, H (catálogos) → G (público) → I (responsive) → J.
- Enséñame cada fase al terminarla. Los pagos muéstramelos funcionando en modo de prueba antes de seguir.
- Si una regla de negocio no está en este documento, **no la inventes**: anótala y pregúntame.
- Todo en español, con acentos correctos.
