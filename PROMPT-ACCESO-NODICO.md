# Prompt para Claude Code — Control de acceso operado desde Nódico

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico —el coworking del Instituto Yucateco de Emprendedores— tiene un terminal de reconocimiento facial **HFSecurity FR07** en la entrada, gobernado por un servidor **Smart Pass System** instalado en `C:\SMART_PASS`, en una computadora de la oficina.

**El objetivo:** que todo lo operativo se haga desde Nódico. Recepción debe poder enrolar el rostro de un miembro, abrir la puerta con un botón para una visita o un day-pass, y consultar todos los accesos — sin volver a abrir Smart Pass nunca.

### La regla que ordena todo el diseño

**Smart Pass no se sustituye: se vuelve invisible.**

El FR07 habla únicamente con Smart Pass, por un canal RPC propietario en el puerto 7788. Ese protocolo no está documentado y no se puede reimplementar sin romper el control de acceso físico. Así que Smart Pass se queda como controlador del hardware, corriendo de fondo, y **Nódico se convierte en la única interfaz y en la fuente de verdad**.

Nunca escribas código que intente hablarle directamente al FR07.

### Lo que ya está verificado del entorno

| Dato | Valor |
|---|---|
| Aplicación | Spring Boot, paquete `com.tendcent.face.owl`, sobre Tomcat 8.5 |
| Interfaz y API | `http://localhost:9000` (también HTTPS en 9443) |
| Autenticación | Cookie de sesión, expiración de 10 días |
| Base de datos | MySQL 5.7 en el puerto **3307**, base `tdx_face_owl` |
| Canal del terminal | RPC en el puerto 7788 |
| Módulos relevantes | Personal → Empleado · Asistencia → Grabar · Permisos → Pase Monitor · Dispositivo |
| Estado actual | 408 rostros registrados; 372 eventos de «extraño» |

**Importante:** las credenciales de MySQL están hoy en texto plano dentro de los scripts `.bat` de esa carpeta. No las copies al repositorio ni las dejes en ningún archivo versionado. Van en el `.env` del agente, y ese archivo nunca se sube a git.

### El proyecto Nódico

Laravel 12 · Inertia 2 · Vue 3 · Tailwind 3.4, alojado en Hostinger. Ya tiene portal de miembro, portal operativo, modelo `Checkin`, control de bolsas de horas con libro de movimientos, y el registro de day-pass gratuitos para emprendedores del interior.

**Rama:** `feature/control-acceso`, a partir de la rama integrada más reciente.

---

# FASE 0 — Descubrir la API antes de escribir nada

Ningún endpoint de Smart Pass está documentado. **No los adivines.** Esta fase existe para convertir lo desconocido en un contrato escrito, y su resultado decide si las fases 3 y 4 son viables tal como están planteadas.

La interfaz de Smart Pass es una aplicación de una sola página: cada cosa que hace la hace llamando a su propia API. Ábrela en el navegador con las herramientas de desarrollador en la pestaña de red, recorre los módulos y documenta lo que veas.

**Lo que hay que capturar, en orden de importancia:**

1. **Inicio de sesión.** Ruta, cuerpo, y cómo se sostiene la sesión después. Es la puerta de todo lo demás.
2. **Listado de eventos de acceso** (Permisos → Pase Monitor, y Asistencia → Grabar). Ruta, parámetros de filtro por fecha, forma de la respuesta y **qué campo distingue a un miembro reconocido de un «extraño»**.
3. **Alta de persona y carga de rostro** (Personal → Empleado → agregar). Especialmente: si la foto se envía como base64 o como archivo, qué campos son obligatorios, y **qué responde cuando la foto no sirve** — ese mensaje de error es el que Nódico tendrá que traducir a algo entendible.
4. **Apertura remota de la puerta** (busca en Dispositivo). Si existe, documenta la ruta y qué confirma el éxito.
5. **Baja o desactivación de persona**, para cuando alguien deje de ser miembro.

En paralelo, conéctate a MySQL en el 3307 y documenta el **esquema real**: qué tabla guarda los eventos, cuál las personas, cómo se relacionan, qué columna es el identificador incremental y cuál la marca de tiempo. Los eventos los vamos a leer de la base, no de la API.

**Entregable: `docs/SMARTPASS-API.md`** con todo lo anterior. Sin ese documento, las fases siguientes no arrancan.

### Dos preguntas que esta fase tiene que responder, y que pueden cambiar el plan

- **¿Se puede enrolar un rostro mandando una foto por API?** Si el algoritmo exige que la captura la haga el propio terminal, el enrolamiento desde el navegador no va a funcionar y hay que irse al plan alterno de la Fase 3.
- **¿Existe la apertura remota?** Si este modelo o su firmware no la tienen, la Fase 4 cambia por completo.

**Si alguna de las dos es que no, párate y dímelo antes de seguir.** No inventes un rodeo por tu cuenta.

---

# FASE 1 — El agente local

Laravel corre en Hostinger, en internet. Smart Pass corre en una computadora de la oficina, en una red privada. **Hostinger nunca va a poder verlo.** El puente es un agente que vive en esa computadora.

- Escríbelo en **PHP** si puedes reutilizar conocimiento del equipo, o en Python si resulta más simple de empaquetar. Que sea un programa pequeño y aburrido.
- Se instala como **servicio de Windows** — en `C:\SMART_PASS` ya está `nssm.exe`, que sirve justo para eso. Tiene que arrancar solo con la computadora.
- **Solo hace conexiones salientes.** No abre ningún puerto, no requiere tocar el router, no expone nada a internet. Es la única razón por la que este diseño es aceptable con datos biométricos de por medio.
- Se autentica contra Laravel con un secreto compartido y firma cada envío. Laravel rechaza lo que no venga firmado.
- Su configuración —credenciales de MySQL, URL de Laravel, secreto— vive en un `.env` propio, fuera del repositorio.
- Escribe su propio registro rotado, y expone un endpoint local de salud para poder ver si está vivo.

**Aprovecha para dejar Tomcat como servicio también.** Hoy se levanta a mano y muere al cerrar la ventana; si el acceso de Nódico va a depender de él, tiene que arrancar solo. Documenta ambos servicios en `docs/ACCESO-INSTALACION.md`.

---

# FASE 2 — Los accesos llegan a Nódico

El agente consulta la base de Smart Pass cada pocos segundos por eventos con identificador mayor al último procesado, y los envía a Laravel.

**Reglas que no se negocian:**

- **Idempotencia.** El identificador del evento en Smart Pass viaja con cada envío y Laravel lo guarda. Un evento repetido se descarta en silencio. Sin esto, un reintento duplica check-ins y regala horas.
- **Cola local.** Si Laravel no responde —se cayó internet, Hostinger está lento— el agente guarda y reintenta con espera creciente. Nadie pierde su acceso porque falló la red.
- **Los «extraños» no son check-ins.** El terminal registra caras que no reconoce. Esos eventos se guardan como tales, para poder revisarlos, pero jamás generan asistencia ni consumen días de membresía.
- **Marca de tiempo del evento, no de la recepción.** Si el agente estuvo caído tres horas, los eventos entran con la hora en que ocurrieron.

En Laravel: una tabla propia de eventos de acceso, con el identificador de origen, el momento, la persona, el dispositivo, el tipo de reconocimiento y el resultado. De ahí se derivan los `Checkin` que ya existen, y de ahí se incrementa `dias_usados` una sola vez por día natural.

**La unión entre los dos sistemas** es un campo en el usuario de Nódico que guarda su identificador de persona en Smart Pass. Sin ese amarre, un evento es solo «entró la persona 147» y no significa nada.

---

# FASE 3 — Enrolar el rostro desde Nódico

Pantalla nueva en el portal operativo, dentro de la ficha del miembro.

**El camino preferente:** recepción captura la foto con la cámara de la computadora o la tableta desde el propio navegador, ve la vista previa, confirma, y Nódico la manda al agente, que la registra en Smart Pass y devuelve el identificador de la persona para guardarlo en el miembro.

**Antes de capturar nada, un muro:** si el miembro no tiene registrado su **consentimiento expreso para tratamiento de datos biométricos**, la pantalla no deja continuar. No es un aviso que se pueda saltar. Los datos biométricos son datos personales sensibles y su tratamiento exige consentimiento expreso, separado del consentimiento general que ya existe en el registro. Guarda constancia con versión, fecha y quién la recabó, igual que se hizo con el aviso de privacidad.

**Sobre la foto:** decide con criterio de privacidad si Nódico la conserva o solo la reenvía y la descarta. Mi recomendación es no guardarla: el rostro ya vive en Smart Pass, duplicarlo solo duplica el riesgo. Si se guarda, que sea cifrada y con política de retención escrita.

**Estados y errores.** El algoritmo va a rechazar fotos: mal iluminadas, de perfil, con lentes oscuros, con más de una cara. Traduce cada rechazo a una instrucción concreta —«acércate más», «quítate los lentes», «que no haya nadie más en cuadro»— y permite reintentar sin recargar. Que recepción nunca vea un error crudo del fabricante.

**El plan alterno**, si la Fase 0 determinó que no se puede enrolar por API: la persona se enrola en el terminal como siempre, y Nódico ofrece una pantalla para **buscar y vincular** esa persona recién creada con el miembro correspondiente. Menos cómodo, pero cumple el objetivo de que el amarre y el registro vivan en Nódico.

---

# FASE 4 — Abrir la puerta desde Nódico

Un botón en el tablero operativo para dejar entrar a alguien sin rostro registrado: una visita, un day-pass, un proveedor.

**Esto es una acción de seguridad física, y se trata como tal:**

- Solo roles autorizados. Recepción sí; un miembro, jamás.
- **Pide motivo obligatorio** y a quién se le abre. Si es un day-pass gratuito del interior, engancha con el registro que ya existe para ese programa y no captures los datos dos veces.
- **Cada apertura queda en la bitácora** con quién la ordenó, cuándo y por qué. Esto no es burocracia: es lo que permite responder a la pregunta «¿quién dejó entrar a esta persona el martes?».
- Límite de frecuencia, para que un clic accidental repetido no deje la puerta abierta.
- Confirmación visible de si la orden llegó al equipo o falló. Un botón que no dice qué pasó es peor que no tener botón.

Si la Fase 0 encontró que no existe apertura remota en la API, **detente y dímelo**. La alternativa sería por hardware —un relevador en la chapa— y esa es una decisión de Nódico, no tuya.

---

# FASE 5 — La pantalla de accesos

En el portal operativo, la vista que sustituye a Smart Pass para el día a día:

- **Quién está dentro ahora**, con hora de entrada.
- **Registro completo** con filtros por persona, fecha, dispositivo y tipo de evento, y exportación.
- **Eventos no reconocidos** en su propia bandeja, para revisar y, si procede, vincular a una persona.
- **Estado del sistema, visible:** si el agente lleva más de unos minutos sin reportar, o si Smart Pass no responde, tiene que decirlo con claridad en pantalla. Un tablero que muestra cero accesos porque el agente está caído es peor que uno que avisa que está caído.
- En la ficha de cada miembro: su historial de accesos y si tiene rostro enrolado.

Todo esto tiene que funcionar bien en tableta, que es como se usa en el mostrador.

---

# FASE 6 — Que aguante los días malos

- **Si el agente se cae**, recepción sigue pudiendo hacer check-in manual —ya existe— y los eventos se recuperan cuando vuelva, porque se leen por identificador incremental y no por reloj.
- **Si Smart Pass se cae**, el FR07 sigue abriendo la puerta con las caras que ya tiene guardadas; se pierde el registro central hasta que vuelva. Documenta esto: es importante que Nódico sepa que la puerta no depende de la computadora.
- **Si Hostinger se cae**, el agente encola.
- **Nunca** expongas Smart Pass ni su MySQL a internet, ni siquiera «temporalmente para probar».
- Rota la contraseña de root de MySQL y sácala de los `.bat`. Desactiva la consola de Druid en `/druid/*` y el envío de rastreos de error, que hoy están habilitados.

---

# FASE 7 — Pruebas

- Un evento repetido no genera dos check-ins.
- Un evento de «extraño» no genera check-in ni consume día.
- Con Laravel inalcanzable, el agente encola y al volver entrega todo, en orden y sin duplicar.
- El enrolamiento se niega sin consentimiento biométrico registrado.
- La apertura remota exige rol autorizado y motivo, y queda en bitácora.
- Un miembro no puede consultar accesos de otro.
- Un evento con hora vieja entra con su hora real, no con la de recepción.

---

# FASE 8 — Puesta en marcha

Documenta en `docs/ACCESO-INSTALACION.md`: cómo se instalan los dos servicios, cómo se ve que están vivos, cómo se reinicia todo tras un corte de luz, y qué hacer si el agente deja de reportar.

Y algo que hay que decir en voz alta: **esa computadora pasa a ser infraestructura crítica**. Si se muere, se pierde el enrolamiento y el registro central. Deja documentado el respaldo de la base `tdx_face_owl` —los scripts de exportación ya están en `C:\SMART_PASS\db`— y prográmalo.

---

## FORMA DE TRABAJAR

- **La Fase 0 va primero y sola.** Enséñame `docs/SMARTPASS-API.md` y espera mi visto bueno antes de escribir una línea del agente.
- Si el enrolamiento por API o la apertura remota no existen, **para y dímelo** con las opciones. No improvises un rodeo.
- Después: agente → sincronización → pantallas. Enséñame cada fase al terminarla.
- No inventes reglas de negocio ni de seguridad física. Lo que no esté aquí, pregúntalo.
- Todo en español, con acentos correctos.
