# Prompt para Claude Code — Reconexión automática del terminal FR07

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

El terminal de reconocimiento facial **HFSecurity FR07** (`AA312DF45B59A915`, hoy en `192.168.10.4`) pierde la sesión con su servidor **Smart Pass** cada tanto y **no la reestablece por su cuenta**: se queda «Sin conexión» hasta que una persona entra a la plataforma y lo empuja.

**El síntoma revela la causa.** El truco que funciona hoy es entrar a *Dispositivo → Detalle → Red*, reescribir **la misma** contraseña de comunicación LAN y pulsar **Grabar**. La contraseña no estaba mal: lo que hace ese guardado es enviar una orden de configuración que **obliga al equipo a registrarse de nuevo**. Es un reinicio de sesión disfrazado.

Es decir: el equipo está en la red, el servidor está bien y las credenciales son correctas. Lo que muere es la sesión permanente.

**La causa de fondo es física.** El enlace wifi del terminal pierde el **12 % de los paquetes**, con tiempos que van de 7 a 388 ms y cortes de varios segundos. Una conexión TCP permanente no sobrevive a eso. La solución de verdad es **cable de red y direcciones fijas**.

**Esto que vas a construir es una red de seguridad, no el arreglo.** Convierte una caída de horas en una de segundos mientras se resuelve lo físico. Escríbelo sabiendo eso.

**Stack del agente:** PHP puro, sin dependencias, corriendo como servicio de Windows con `nssm` en la computadora del servidor (`C:\SMART_PASS`). Ya existe en `agente-acceso/agente.php`, lee `tdx_pass_record` por id incremental y empuja los eventos a Laravel firmados con HMAC.

**Rama:** `feature/reconexion-terminal`.

---

## ⛔ Lo que NO hay que hacer, y por qué

**No dispares el guardado cada 30 segundos.**

1. **Es una escritura, no un ping.** Guardar configuración escribe en la memoria flash del equipo, que tiene ciclos de escritura limitados. Hacerlo 2 880 veces al día puede desgastarla o corromperla. Estarías rompiendo el equipo por mantenerlo vivo.
2. **Puede interrumpir un reconocimiento en curso.** Si alguien está frente al lector cuando llega la orden, la puerta puede no abrir. Es una consecuencia física, no un error de software.
3. **Esconde el problema en lugar de medirlo.** Si se reconecta solo cada rato y nadie se entera, nunca vas a saber cuántas veces al día se cae — que es justo el dato que necesitas para justificar el cable o reclamar el firmware al proveedor.

**Lo correcto: leer seguido, escribir poco.** Consultar el estado es barato y no desgasta nada. La escritura se reserva para cuando de verdad hace falta.

---

# FASE 0 — Documentar la llamada real

No la inventes. La interfaz de Smart Pass hace esa llamada al pulsar «Grabar»; hay que capturarla.

Abre la plataforma con las herramientas de desarrollador en la pestaña de red, ve a *Dispositivo → Detalle → Red*, pulsa **Grabar**, y documenta:

- Ruta exacta, método y cuerpo completo de la petición.
- Qué campos son obligatorios y cuáles puede rellenar el agente sin conocer estado previo.
- **Qué devuelve cuando tiene éxito y qué devuelve cuando falla** — hay que poder distinguirlo sin adivinar.
- Si existe una llamada **más barata** que consiga lo mismo. Revisa el menú **Control remoto** de la lista de dispositivos: si ahí hay un «reiniciar» o un «sincronizar», puede ser preferible a escribir configuración.
- Y por separado: la llamada de **solo lectura** que devuelve el estado del dispositivo (en línea / sin conexión y la hora de última actividad). Esa es la que se va a usar cada 30 segundos.

**Entregable:** una sección nueva en `docs/SMARTPASS-API.md` con ambas llamadas.

> Esta captura sí tiene efecto sobre el equipo. Hazla **conmigo presente**, una sola vez, y avísame antes.

---

# FASE 1 — Detectar, que es leer

- El agente consulta el **estado del dispositivo** cada **30 segundos**. Es una lectura: no escribe nada, no desgasta nada.
- Se considera caído cuando lo reporta sin conexión en **dos consultas seguidas** (un minuto). Una sola lectura fallida puede ser un paquete perdido, no una caída — y con este enlace, eso pasa el 12 % de las veces.
- Registra cada cambio de estado con su hora: cuándo cayó y cuándo volvió. Ese registro es el producto más valioso de todo esto.

---

# FASE 2 — Reconectar, con freno

Cuando se confirma la caída, el agente dispara **una** orden de reconexión. Y después se comporta con cabeza:

- **Espera creciente entre intentos:** 1 minuto, 5, 15, 30. Si a la primera no volvió, insistir cada minuto no lo va a traer.
- **Tope por hora.** Máximo **6 reconexiones en una hora**. Al llegar al tope, **deja de intentar y avisa**. Si un equipo necesita veinte reconexiones por hora, el problema no se arregla con más reconexiones — y seguir escribiéndole configuración es justamente lo que no queremos.
- **Verifica que sirvió.** Tras cada intento, espera y vuelve a leer el estado. Si volvió, reinicia los contadores. Si no, pasa al siguiente escalón de espera.
- **Nunca dos órdenes a la vez.** Si un intento sigue en curso, el siguiente no arranca.

---

# FASE 3 — No estorbar

- **Ventana de silencio:** si en los últimos segundos hubo un reconocimiento o una apertura de puerta, **pospón la orden**. Nadie se puede quedar afuera porque el agente eligió ese momento para reconfigurar.
- **Fuera del horario de operación** —lunes a viernes de 9 a 19— baja la frecuencia de la consulta. De madrugada no hay nadie que sufra la caída, y conviene dejar descansar al equipo.
- Si el propio Smart Pass no responde, **no intentes nada**: el problema está antes, y el agente debe reportarlo como tal.

---

# FASE 4 — Que el número sea visible

Esto es lo que convierte el parche en evidencia útil.

- Cada caída y cada reconexión se guardan en Nódico con su hora y su resultado.
- En la pantalla de **Accesos** del panel, un indicador honesto: **«el terminal se ha reconectado N veces hoy»**, con el tiempo total que estuvo caído.
- Si se alcanza el tope por hora, o si la reconexión falla varias veces seguidas, **alerta visible** — no un renglón en un log que nadie abre.
- Un reporte simple: reconexiones por día de la última quincena.

**Ese número es el argumento.** «El lector se cayó 14 veces ayer y estuvo 40 minutos sin registrar» consigue un cable de red mucho más rápido que «a veces se desconecta».

---

# FASE 5 — Configuración y secretos

- Todo parametrizable desde el `.env` del agente: intervalo de consulta, umbral de caída, escalones de espera, tope por hora, ventana de silencio y horario.
- **Apagado por defecto**, detrás de un interruptor. Se enciende cuando esté validado.
- La contraseña de comunicación LAN va **solo en el `.env` del agente**, nunca en el repositorio. Hoy es el valor de fábrica `12345678`: déjalo anotado como pendiente de cambiar **de forma coordinada** una vez que esto funcione, porque cambiarla antes rompe la reconexión.
- Sigue siendo todo hacia afuera: el agente no abre ningún puerto ni expone nada a internet.

---

# FASE 6 — Pruebas

- Una sola lectura fallida no dispara reconexión; dos seguidas sí.
- Tras un intento fallido, el siguiente respeta la espera creciente.
- Al llegar al tope por hora, deja de intentar y avisa.
- Con un reconocimiento reciente, la orden se pospone.
- Si Smart Pass no responde, no se intenta nada contra el dispositivo.
- Dos órdenes no pueden solaparse.
- El contador diario cuadra con los cambios de estado registrados.

Y una prueba real, contigo presente: desconecta el terminal de la red, comprueba que el agente lo detecta y lo reconecta solo, y que queda registrado.

---

## LO QUE ESTO NO ES

Déjalo escrito en `docs/ACCESO-INSTALACION.md`, con todas sus letras:

> Esta reconexión automática es un parche. La causa es un enlace wifi con 12 % de pérdida de paquetes. La solución es cable de red y reserva de DHCP para el terminal y para el servidor. Mientras eso no se haga, el equipo va a seguir cayéndose — solo que ahora se levanta solo y queda registrado.

## FORMA DE TRABAJAR

- La Fase 0 primero y sola. Enséñame lo documentado y espera mi visto bueno antes de escribir código.
- Si encuentras en «Control remoto» una llamada más barata que escribir configuración, **prefiérela** y dime por qué.
- No subas el tope de reconexiones ni bajes el intervalo «para que funcione mejor». Si hace falta, es que el problema es otro.
- Todo en español, con acentos correctos.
