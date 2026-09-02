# Prompt para Claude Code — Tablero de ocupación en vivo

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico es el coworking del Instituto Yucateco de Emprendedores. Quiero un **tablero del plano del espacio, en vivo**, para colgar en una televisión en zona pública y para consultar en una tableta: que cualquiera vea de un vistazo qué está libre, qué está ocupado y hasta qué hora — y que al tocar un espacio aparezca su agenda del día para revisar huecos sin preguntarle a nadie.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4. Sistema de diseño en `.claude/skills/nodico-design/SKILL.md`.

**Rama:** `feature/tablero-espacios`.

### La regla que ordena todo el diseño

**La pantalla va en zona pública, a la vista de miembros y visitantes. El tablero no muestra nombres, nunca.**

Ni quién reservó, ni quién está dentro, ni el correo, ni la empresa. Solo el estado del espacio y la hora. Que la sala 2 esté ocupada de 10 a 12 es información del espacio; que la haya reservado Ana es información de Ana.

Recepción ya tiene la agenda completa con nombres dentro del panel (`agenda.index`). **Este tablero no la sustituye**: es la versión pública.

### Los espacios, tal como están dados de alta

Diez espacios reservables, ya en base de datos con su `TipoEspacio`:

| Espacio | Tipo | Capacidad |
|---|---|---|
| Área de Coworking General | `coworking` | 70 |
| Cubículo Privado 1 · 2 · 3 · 4 | `privado` | 4 c/u |
| Sala de Juntas 1 | `sala_juntas` | 12 |
| Sala de Juntas 2 | `sala_juntas` | 8 |
| Sala de Creación de Contenido / Podcast | `contenido` | 4 |
| Sala de Fotografía | `fotografia` | 6 |
| Yucatán Emprende 1 · 2 | `salon_eventos` | 120 c/u |

### El plano

En la conversación te paso el plano real. Su distribución:

- **Piso principal:** entrada arriba a la derecha, recepción, área de descanso y comedor con cafetería, zona de eventos arriba a la izquierda, la columna de oficinas por el costado izquierdo, y el espacio de trabajo abierto al centro con tres mesas largas.
- **Tres islas, fuera del piso principal:** **Sala de Juntas 2**, **Sala de Podcast** y **Sala de Fotografía**. Van dibujadas aparte, separadas del contorno, porque físicamente están fuera de Nódico.
- El cuarto sin nombre entre «Sala de juntas 1» y «Oficina 04» **no se reserva** — es servicio. Se dibuja como parte del plano pero inerte: sin estado, sin color, sin toque.

---

# FASE 1 — El plano, en SVG de verdad

**No uses una imagen con zonas calientes encima.** Dibuja el plano como **SVG en línea**, con cada espacio reservable como una región propia identificada por el `id` de su registro en `espacios`. Solo así se le puede cambiar el color según el estado y hacerlo tocable sin que se descuadre al redimensionar.

- Fiel a la distribución del plano, no un diagrama abstracto: quien lo vea debe reconocer dónde está parado.
- Los elementos que no se reservan —entrada, recepción, comedor, cafetería, cuarto de servicio, pasillos— van dibujados en gris neutro, sin estado y sin interacción. Dan contexto para orientarse.
- Las tres islas, separadas y con su propio marco.
- El **espacio de trabajo** son las tres mesas largas: se pintan como una sola zona con su porcentaje de ocupación, no como asientos individuales.
- Escala fluida con `viewBox`, sin anchos fijos: la misma pieza tiene que verse bien en una tele de 55 pulgadas y en una tableta de 10.

---

# FASE 2 — Los estados, y de dónde sale cada dato

Cinco estados, y cada uno tiene que distinguirse **por color y por forma**, no solo por su etiqueta: a cuatro metros de distancia nadie lee texto pequeño.

| Estado | Qué significa | De dónde sale |
|---|---|---|
| **Libre** | Nadie ahora ni en la próxima media hora | sin reserva vigente |
| **Ocupada** | Hay reserva corriendo ahora | `reservas` confirmadas de hoy |
| **Aparta pronto** | Se ocupa en menos de 30 minutos | próxima reserva del día |
| **Bloqueada** | Mantenimiento o evento privado | `bloqueos_espacio` |
| **Fuera de horario** | Cerrado | horario de operación y días festivos |

**En «Ocupada», lo único que importa es hasta qué hora se libera.** Esa cifra va grande. Es la pregunta real de quien mira el tablero.

## 2.1 · El porcentaje del espacio de trabajo

Sale de los check-ins activos contra la capacidad de 70. **Y aquí hay una trampa que hay que resolver, no ignorar:** si alguien entra y nunca marca salida, el porcentaje se queda inflado y el tablero miente el resto del día.

- Cierra automáticamente los check-ins abiertos a la hora de cierre.
- Si un check-in lleva más horas abiertas que la jornada, no lo cuentes y regístralo para que recepción lo revise.
- Y muestra el número como lo que es: **personas dentro**, no escritorios ocupados. No prometas una precisión que no tienes.

## 2.2 · Los salones de eventos

El plano dibuja una sola «Zona de eventos», pero el sistema tiene dados de alta **Yucatán Emprende 1 y 2**, ambos con capacidad 120 y datos idénticos. Es la misma discrepancia que quedó abierta en la auditoría del sitio.

**No la resuelvas por tu cuenta.** Dibuja la zona de eventos como un solo bloque que refleje el estado combinado, y déjalo anotado como pendiente: hay que saber si son dos salas reales o una divisible.

---

# FASE 3 — El tablero en la televisión

Es lo primero que va a ver alguien al entrar. Que se vea bien.

- **Plano protagonista**, ocupando la mayor parte de la pantalla.
- **Una franja de resumen**: cuántas salas libres de cuántas, el porcentaje del espacio de trabajo, y la hora. Cifras grandes, de las que se leen desde la puerta.
- **Leyenda de colores** siempre visible, discreta. Sin ella, los colores no significan nada para quien llega por primera vez.
- **Reloj y fecha**, porque una pantalla colgada sin hora se siente muerta.
- **«Actualizado hace X»** visible siempre. Es lo que le dice a quien mira si puede confiar en lo que ve.

**Sobre el color:** la paleta de Nódico es crema y amarillo, y en una televisión a cuatro metros eso tiene poco contraste. Haz una **variante oscura para pantalla**, sobre el `tinta` de la marca, con los estados en colores que se distingan a distancia. Verifícalo de verdad: aléjate del monitor o reduce la imagen al 25 % y comprueba que sigues distinguiendo libre de ocupado.

**Y considera al daltonismo:** cerca del 8 % de los hombres no distingue rojo de verde. Que el estado se lea también por relleno, trama o icono, no solo por color.

---

# FASE 4 — El toque: la agenda del día

Al tocar un espacio se abre su agenda de hoy. **Este es el corazón de lo que se pidió**: revisar disponibilidad rápido, sin preguntar.

- **Línea de tiempo del día**, de la apertura al cierre, con los bloques ocupados marcados.
- **Los huecos libres son los protagonistas**, no los ocupados. Que salten a la vista: son lo que la persona está buscando.
- Cada bloque ocupado dice solo su horario. **Sin nombres.**
- Nombre del espacio, capacidad y para qué sirve.
- Un **código QR** que lleve a reservar ese espacio en el portal. Quien esté viendo el tablero tiene el teléfono en la mano: que pueda apartar el hueco ahí mismo en lugar de ir a buscar a recepción.
- Cerrar con toque fuera, con una equis grande y **solo también**: si nadie interactúa en 45 segundos, vuelve al plano. Una pantalla colgada no puede quedarse atorada en el detalle de una sala porque alguien la tocó al pasar.

En tableta esto se toca con el dedo: áreas de al menos 44 px, y el panel de detalle a pantalla completa en vertical.

---

# FASE 5 — Que aguante colgado semanas

Un tablero de pared no se reinicia. Va a estar abierto meses.

- **Se actualiza solo** cada 30 segundos. Sin recargar la página entera: solo los datos.
- **Sin fugas de memoria.** Nada de arreglos que crecen sin tope ni temporizadores que se acumulan. Si el navegador se queda sin memoria a los tres días, el tablero no sirve.
- **Sobrevive a que se caiga la red.** Reintenta con espera creciente y **dice en pantalla que está desconectado**. Un tablero que muestra datos de hace dos horas como si fueran de ahora es peor que uno apagado: alguien va a confiar en él.
- **Sobrevive a que la tableta se duerma.** Al despertar, refresca de inmediato en vez de mostrar lo de anoche.
- **Cuida la pantalla.** Las teles con imagen fija se queman. Desplaza el contenido unos píxeles cada cierto rato, y **atenúa fuera del horario de operación** en vez de mantener el brillo toda la noche.
- **Fuera de horario**, di que está cerrado y a qué hora abre. No muestres todo libre a las tres de la mañana como si se pudiera entrar.
- **Respeta `prefers-reduced-motion`** en cualquier animación.

---

# FASE 6 — Cómo se abre sin iniciar sesión

Una televisión colgada no puede pedir contraseña, y nadie va a reiniciar sesión cada mañana.

- Ruta pública propia, protegida por un **token largo y aleatorio** en la URL, que se genera desde el panel y se puede revocar.
- **Solo lectura.** Ese token no autoriza nada más: ni ver miembros, ni reservar, ni tocar el panel.
- **Que no exponga nada sensible ni por accidente.** Revisa la respuesta del servidor: si en el JSON viajan nombres o correos «por si acaso», ahí hay una fuga aunque la interfaz no los pinte. Manda solo lo que el tablero dibuja.
- Límite de peticiones, y sin indexación en buscadores.
- En el panel, una pantalla para generar el enlace, ver cuándo se usó por última vez y revocarlo.

Piensa en el peor caso: alguien fotografía la URL. Lo máximo que debería conseguir es saber qué salas están ocupadas — molesto, no grave. Si consigue algo más, está mal diseñado.

---

# FASE 7 — Pruebas

- Una reserva que corre ahora pinta el espacio como ocupado, y termina de pintarlo cuando pasa su hora.
- Un bloqueo por mantenimiento gana sobre todo lo demás.
- Fuera de horario y en día festivo, el tablero lo dice.
- La respuesta del endpoint público **no contiene ningún nombre, correo ni identificador de persona** — pruébalo contra el JSON, no contra la pantalla.
- El token revocado deja de funcionar de inmediato.
- Un check-in abierto más allá del cierre no infla el porcentaje.
- El detalle de un espacio se cierra solo a los 45 segundos.
- Con la red caída, el tablero avisa en vez de mostrar datos viejos como buenos.

Además: `npm run build` y `php artisan test` limpios, y pruébalo **en los tamaños reales** — 1920×1080 de televisión, iPad vertical y horizontal.

---

# FASE 8 — Despliegue

Sigue `docs/DEPLOY.md`: Hostinger no tiene Node y hay que sincronizar las dos rutas de `build/`.

Documenta en `docs/TABLERO.md` cómo se genera el enlace, cómo se deja la tele en pantalla completa y en modo quiosco, y qué hacer si el tablero se queda en blanco.

---

## DECISIONES QUE NO DEBES TOMAR SOLO

- **«Oficina» o «Cubículo Privado».** El plano dice Oficina 01-04; el sistema dice Cubículo Privado 1-4. El tablero tiene que usar el nombre que la gente dice en voz alta. Pregúntame cuál gana, y si hay que renombrarlos en la base.
- **Los dos Yucatán Emprende**, según la Fase 2.2.
- **Si el tablero debe mostrar los salones de eventos.** Se rentan a gente de fuera; quizá no interese anunciar su ocupación en la pantalla pública.

## FORMA DE TRABAJAR

- Muéstrame el plan y **un boceto del SVG del plano** antes de conectar ningún dato. Si el plano no se reconoce, lo demás da igual.
- Después: estados y datos → tablero → toque y agenda → resistencia.
- Enséñame capturas en 1920×1080 y en iPad al terminar cada fase.
- **No inventes espacios ni estados** que no existan en la base. Lo que falte, pregúntalo.
- Todo en español, con acentos correctos.
