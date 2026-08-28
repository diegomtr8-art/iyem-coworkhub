# Prompt para Claude Code — Fase 2: rediseño del sitio público de Nódico + deploy

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`, sobre la rama `feature/clone-sitio-publico`.

---

## CONTEXTO

El clon funcional del sitio de Nódico ya está hecho y desplegado en `https://prueba.nodico.com.mx`. Ahora viene la **fase de diseño**: el sitio actual es correcto pero soso — se ve a "página institucional de gobierno". Quiero que se vea **excelente**.

**Público objetivo:** chavos de 18 a 30 años que están empezando a emprender en Yucatán. El tono debe ser **juvenil, tecnológico y empresarial** a la vez: con energía y personalidad, pero que un banco o un aliado corporativo lo vea y lo tome en serio. Nada de corporativo aburrido, nada de infantil.

Tienes **libertad creativa total** dentro de la identidad de marca. No me preguntes por cada decisión estética: propón, ejecuta y muéstrame el resultado.

### Estado del proyecto (ya existe, no lo rehagas desde cero)

- Laravel 12 + Inertia 2 + Vue 3 (`<script setup lang="ts">`) + Tailwind 3 + Vite 7
- Rama de trabajo: `feature/clone-sitio-publico`
- Páginas: `Welcome.vue`, `Nosotros.vue`, `Membresias.vue`, `Salones.vue`, `Comunidad.vue`
- Componentes: `resources/js/Components/Public/` → `SiteHeader`, `SiteFooter`, `ContactSection`, `PlanCard`, `IconCard`, `SectionHeading`, `ScrollReveal`, `StagingBanner`
- Layout: `resources/js/Layouts/PublicLayout.vue`
- Imágenes ya descargadas en `public/img/nodico/`
- Contenido en BD vía `NodicoWebSeeder` (planes con `stripe_url` y beneficios, salones)
- Fuentes de marca cargadas en `resources/css/app.css`: **Carmen Sans** (`font-display`) y **GT Eesti Pro Display** (`font-body`)
- Tokens en `tailwind.config.js`: `nodo.400 #FFE124`, `dark #2E2D2C`, `cream #F4F1EA`, y `plan.flex #FFDD00` / `plan.pro #D6E265` / `plan.daypass #EF7E88` / `plan.match #864B95`
- Deploy documentado en `docs/DEPLOY.md` (Hostinger, sin Node en el servidor, `deploy_prueba.py` por SFTP)

---

## ANTES DE EMPEZAR

1. Revisa qué **skills** tienes disponibles y usa las que apliquen a este trabajo (diseño/frontend, revisión de código, seguridad). Al terminar, corre **`/security-review`** sobre el diff antes del deploy.
2. Con **`skill-creator`**, crea una skill del proyecto llamada `nodico-design` que capture el sistema de diseño que definas en la Tarea A (tokens, tipografía, espaciado, patrones de componente, reglas de movimiento y de responsive). Así el siguiente cambio de UI sale consistente sin repetir este prompt.
3. Léete `docs/AUDITORIA-NODICO.md` y `docs/DEPLOY.md` antes de tocar nada.
4. Muéstrame tu **propuesta de dirección de arte en texto** (paleta extendida, escala tipográfica, 3 o 4 patrones visuales que vas a usar) y espera mi visto bueno. Después ya no me consultes cada detalle.

---

## TAREA A — Sistema de diseño

La marca ya tiene amarillo, crema y carbón. Eso es la base, **no es suficiente**: hoy todo se ve plano porque no hay jerarquía visual, ni profundidad, ni ritmo.

Construye una capa de diseño encima:

- **Escala tipográfica dramática.** Carmen Sans en tamaños grandes de verdad para los titulares, con `clamp()` para que escale fluido de iPhone a desktop. Contraste fuerte entre titular y cuerpo. Interlineado ajustado en los títulos (`leading-[0.9]`), amplio en el texto.
- **Profundidad.** Nada de tarjetas planas sobre fondo plano. Usa bordes definidos, sombras con intención, superposición de capas, y elementos que se salen de su contenedor.
- **Ritmo y alternancia.** Alterna secciones crema / carbón / amarillo para que la página tenga respiración y no sea un scroll monótono. Ninguna sección debe verse igual que la anterior.
- **Detalle técnico.** Numeración de secciones tipo `01 — SERVICIOS`, etiquetas monoespaciadas en mayúsculas, líneas de retícula sutiles, badges tipo chip. Esto es lo que da el aire "tech" sin caer en el cliché de circuitos y hologramas.
- **Movimiento.** Aparición al hacer scroll (ya tienes `ScrollReveal.vue`, mejóralo con stagger), un marquee de texto con las palabras de la marca (ya hay keyframes `marquee` en Tailwind), y micro-interacciones en hover/tap: elevación, cambio de color, desplazamiento del icono en las flechas.
- **Regla de oro:** todo el movimiento debe respetar `prefers-reduced-motion: reduce` y desactivarse por completo si está activo.

Amplía `tailwind.config.js` con lo que necesites (sombras, tamaños, easings) en vez de regar valores arbitrarios por los componentes.

---

## TAREA B — Hero con video

El video oficial es **`https://youtu.be/Ml4sprGUqzc`** (ID: `Ml4sprGUqzc`).

Crea `resources/js/Components/Public/HeroVideo.vue`:

- **Desktop / iPad:** el video corre de fondo, en silencio, en bucle, sin controles, con una capa oscura encima para que el titular se lea. Parámetros: `autoplay=1&mute=1&loop=1&playlist=Ml4sprGUqzc&controls=0&modestbranding=1&rel=0&playsinline=1`, sobre el dominio `youtube-nocookie.com`.
- **iPhone:** iOS bloquea de forma inconsistente el autoplay en iframes. Ahí muestra la imagen `hero-inicio.webp` como fondo y un botón de play prominente.
- **En ambos:** botón "Ver el video" que abre el video **con sonido y controles** en un modal accesible (cierra con Esc, atrapa el foco, bloquea el scroll del fondo).
- **Rendimiento:** no cargues el iframe hasta que haga falta — poster primero, iframe al entrar en viewport o al hacer clic (patrón *facade*). El hero no debe costarle medio megabyte a nadie.
- Encima: el titular actual ("Bienvenidos al lugar / Donde el trabajo es un pretexto para crear") con la nueva escala tipográfica, un subtítulo, y dos CTAs — primario "Conocer membresías" → `/membresias`, secundario "Conocer más" → `/nosotros`.

---

## TAREA C — Carrusel de membresías

Reemplaza la reja estática de 4 tarjetas por un **carrusel real**. Crea `PlanesCarousel.vue`:

- **Nodo Pro es la recomendada.** Debe verse claramente destacada: badge "⭐ LA MÁS POPULAR" o "RECOMENDADA", tarjeta más grande que las demás, borde de acento, y arrancar centrada en el carrusel.
- **Comportamiento:** desplazamiento con snap (`scroll-snap-type: x mandatory`), arrastre con mouse y swipe táctil, autoplay suave que se detiene al interactuar o al pasar el cursor, flechas en desktop y puntos indicadores en móvil.
- **Cuántas se ven:** iPhone 1 tarjeta con un asomo de la siguiente (para que se note que hay más), iPad 2, desktop 3 con la central destacada.
- **Cada tarjeta:** franja superior con su color de `plan.*`, nombre, precio grande, periodo, descripción, lista de beneficios con palomita, y CTA a su enlace de Stripe (`target="_blank" rel="noopener"`). Los datos salen de la BD, no los hardcodees.
- **Accesibilidad:** navegable con teclado (flechas izquierda/derecha), `aria-roledescription="carousel"`, cada slide con su `aria-label`, y el autoplay se detiene con el foco. Si hay `prefers-reduced-motion`, sin autoplay y sin scroll animado.
- Úsalo tanto en `Welcome.vue` como en `Membresias.vue`.

---

## TAREA D — Logos de aliados

La sección "¿Quieres conocer más de Nódico?" hoy muestra los logos dentro de una caja de color que no corresponde.

- Convierte los tres logos a **PNG con fondo transparente** en `public/img/nodico/` (`logo-iyem.png`, `logo-herencia-viva.png`, `logo-canieti.png`). Recorta el margen sobrante de cada archivo.
- Preséntalos sueltos sobre el fondo de la sección, alineados por su altura óptica —no por su caja—, en escala de grises que pasa a color al hacer hover.
- **Enlaces:**
  - IYEM → `https://iyem.yucatan.gob.mx` (nueva pestaña)
  - Herencia Viva → `https://herenciaviva.com` (nueva pestaña)
  - CANIETI → **sin enlace**, solo el logo
- Los que sí son enlace llevan `target="_blank" rel="noopener noreferrer"` y `alt` descriptivo; el de CANIETI va como `<img>` suelto, no dentro de un `<a>` vacío.
- En iPhone: dos columnas, o una fila con scroll horizontal. Que nunca se aplasten.

---

## TAREA E — Instagram

Cuenta oficial: **`https://www.instagram.com/nodicomx`**

Un dato técnico que debes tener claro antes de implementar: **Instagram no permite embeber el feed completo de un perfil** con su embed público. El embed oficial solo funciona con **publicaciones individuales** (`https://www.instagram.com/p/{shortcode}/embed/`). Traer el feed automático exige la **Instagram Graph API** con cuenta de empresa o creador vinculada a una página de Facebook y un token de larga duración.

Haz esto:

1. Crea `InstagramSection.vue` que reciba una lista de **permalinks de publicaciones** y las renderice como iframes responsivos con `loading="lazy"`, en una reja de 1 columna en iPhone, 2 en iPad y 3 o 4 en desktop.
2. Guarda esos permalinks en la **base de datos** (tabla o entrada de configuración, sembrada en `NodicoWebSeeder`), para que se puedan cambiar sin volver a desplegar. Deja 4 publicaciones actuales del perfil como valor inicial.
3. Encabeza la sección con un bloque de marca: handle `@nodicomx`, una frase corta y un botón "Síguenos en Instagram".
4. Si un embed no carga, muestra una tarjeta de respaldo con el logo y el enlace al perfil. Que un iframe caído nunca deje un hueco en blanco.
5. En `docs/AUDITORIA-NODICO.md` deja anotado el camino para migrar a la Graph API cuando quieran feed automático, con los requisitos de cuenta y token.

---

## TAREA F — Formulario "Hablemos"

Hoy vive dentro de una franja pegada al footer y se pierde. **Sácalo de ahí y conviértelo en su propia sección con peso propio.**

- Sección independiente, con su ancla `#hablemos`, claramente separada del footer.
- **Composición partida:** de un lado los datos de contacto —dirección, teléfono, correo, horarios, redes, y un botón a Google Maps—; del otro, el formulario dentro de una tarjeta elevada que se sobrepone al fondo.
- **Campos:** Nombre, Teléfono, E-mail, Empresa, Asunto, Comentarios. Etiquetas flotantes, foco marcado con el amarillo de marca, y validación en vivo que muestra el error debajo del campo, no en un bloque al final.
- El textarea de Comentarios ocupa el ancho completo.
- **Estados completos:** normal, foco, error, enviando (botón con spinner y deshabilitado), y éxito (mensaje inline además del toast). Nunca dejes al usuario sin saber qué pasó.
- **En iPhone:** los `input` con `font-size: 16px` como mínimo — si es menor, iOS hace zoom automático al enfocar y descuadra la página. Usa el `inputmode` y el `autocomplete` correctos en cada campo para que salga el teclado adecuado.
- Añade un honeypot antihumanos-falsos y limitación de intentos (`throttle`) en la ruta `POST /contacto`. Verifica que `ContactoController` siga enviando a `contacto@nodico.com.mx` y guardando el registro.

---

## TAREA G — Footer nuevo

El footer actual es un placeholder de tres columnas. Rehazlo por completo — es lo último que ve el usuario y hoy no dice nada.

Estructura sugerida (ajústala si se te ocurre algo mejor):

1. **Franja superior de cierre:** un llamado final grande sobre fondo amarillo — algo tipo "¿Listo para empezar?" con CTA a membresías. Que el footer arranque con energía, no con letra chiquita.
2. **Cuerpo oscuro, cuatro columnas** que en iPhone se apilan y en iPad van a dos:
   - Logo blanco de Nódico, una línea de qué es Nódico, y las redes con iconos que reaccionan al hover.
   - Navegación del sitio.
   - Contacto: dirección con enlace a mapa, teléfono con `tel:`, correo con `mailto:`, horarios.
   - Aviso de facturación (correo a `contacto@nodico.com.mx` con asunto "Solicitud de factura") y los logos de aliados en versión pequeña.
3. **Barra inferior:** año dinámico, la leyenda de que Nódico es marca registrada del Instituto Yucateco de Emprendedores, enlaces a aviso de privacidad y términos (créalos como páginas aunque sea con contenido provisional marcado como pendiente), y un botón "volver arriba".
4. Detalle de personalidad: un marquee sutil con palabras de la marca separando el cierre del cuerpo.

En iPhone respeta el área segura del notch con `env(safe-area-inset-bottom)`.

---

## TAREA H — Responsive iPhone y iPad

No basta con que "no se rompa". Pruébalo en serio, con el navegador, en estos anchos:

| Dispositivo | Ancho |
|---|---|
| iPhone SE | 375 px |
| iPhone 15 | 393 px |
| iPhone 15 Pro Max | 430 px |
| iPad mini vertical | 744 px |
| iPad Pro vertical | 1024 px |
| iPad Pro horizontal | 1366 px |

Reglas obligatorias:

- Área táctil mínima de 44×44 px en todo lo que se pueda tocar.
- Cero scroll horizontal en cualquier ancho. Revisa titulares grandes, tablas y el carrusel.
- Menú móvil: pantalla completa, con animación de entrada, que bloquee el scroll del fondo y cierre con Esc, con tap fuera o al navegar.
- Imágenes con `width`/`height` explícitos para que no salte el layout al cargar (CLS).
- `-webkit-tap-highlight-color` ajustado para que no aparezca el flash gris de iOS.
- Prueba también en **orientación horizontal de iPhone**, que es donde se rompen los heroes a pantalla completa.

Toma capturas de cada breakpoint, revísalas tú mismo y corrige lo que se vea mal **antes** de enseñármelas.

---

## TAREA I — Aplicar el rediseño a las cinco páginas

No dejes el rediseño solo en el Inicio. `Nosotros`, `Membresías`, `Salones` y `Comunidad` deben quedar al mismo nivel, con el mismo sistema, cada una con su propia composición para que no se sientan plantillas repetidas.

En `Comunidad.vue` aprovecha para dejar el calendario de Luma bien integrado visualmente (hoy es un iframe crudo) y para que la sección de Instagram conviva con el directorio de emprendedores.

---

## TAREA J — Verificación

Antes de desplegar:

1. `npm run build` y `php artisan test` sin errores.
2. Revisa la consola del navegador: **cero errores y cero warnings** en las cinco páginas.
3. Lighthouse en móvil: apunta a 90+ en Rendimiento y Accesibilidad. Reporta lo que quede por debajo y por qué.
4. Recorre el sitio completo **solo con teclado**: todo alcanzable, foco siempre visible.
5. Verifica contraste AA — ojo especial con texto sobre el amarillo `#FFE124`, que con blanco encima no pasa.
6. Corre **`/security-review`** sobre el diff.
7. Actualiza `docs/AUDITORIA-NODICO.md` con lo que cambió.

---

## TAREA K — Deploy a prueba.nodico.com.mx

Sigue **`docs/DEPLOY.md`** al pie de la letra. Recordatorios críticos de ese servidor:

- El servidor **no tiene Node**: compila en local con `npm run build` antes de subir.
- Los assets van a **dos rutas** y ambas deben quedar sincronizadas: `public_html/build/` y `public_html/public/build/`. Si solo actualizas una, el sitio sirve assets rotos.
- Los estáticos nuevos (imágenes, PNGs de logos, fuentes) van a la **raíz** de `public_html/`, no dentro de `public_html/public/`.
- El CLI corre PHP 8.2 y el sitio PHP-FPM 8.3: que `php artisan` termine bien **no** confirma que la web funcione.
- Nunca sobrescribas el `.env` del servidor.

Procedimiento:

```bash
npm run build
python deploy_prueba.py
```

Y verifica de verdad:

```bash
for r in / /nosotros /membresias /eventos /actividades; do
  echo "$r -> $(curl -s -o /dev/null -w '%{http_code}' https://prueba.nodico.com.mx$r)"
done
curl -s https://prueba.nodico.com.mx/robots.txt   # debe decir "Disallow: /"
```

Después abre `https://prueba.nodico.com.mx` en el navegador, revísala en ancho de iPhone y de iPad, confirma que el video, el carrusel, los embeds de Instagram y el formulario funcionan **en el servidor** (no solo en local), y mándame el resumen.

---

## FORMA DE TRABAJAR

- Rama `feature/rediseno-publico` a partir de `feature/clone-sitio-publico`. Commits pequeños en español.
- Orden: dirección de arte → sistema de diseño → Inicio completo → **me lo muestras** → el resto de las páginas → verificación → deploy.
- No toques `/dashboard`, `/portal` ni autenticación.
- Todo el texto en español con acentos correctos.
- No inventes contenido: si falta una foto, un dato o un permalink de Instagram, ponlo en una lista de pendientes y pregúntame.
- Si algo del diseño no te convence al verlo renderizado, arréglalo sin preguntar. Tienes libertad creativa; úsala.
