# Prompt para Claude Code — Fase 3: corregir la auditoría, rehacer la portada y desplegar

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

El sitio público de Nódico ya está reconstruido en Laravel + Inertia y desplegado en `https://prueba.nodico.com.mx`. Se le hizo una auditoría completa de código, imágenes, datos y despliegue: **50 hallazgos**. Este trabajo tiene tres partes: corregirlos todos, rehacer varias secciones de la portada, y desplegar.

**Público objetivo:** chavos de 18 a 30 años empezando a emprender en Yucatán. Juvenil, tecnológico y empresarial a la vez.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4.19 · Vite 7. Sistema de diseño «editorial técnico» documentado en `.claude/skills/nodico-design/SKILL.md` y en `tailwind.config.js`. Fuentes de marca: Carmen Sans (`font-display`) y GT Eesti Pro Display (`font-body`).

**Antes de empezar:**

1. Rama `feature/auditoria-y-portada` a partir de `feature/clone-sitio-publico`.
2. Lee `.claude/skills/nodico-design/SKILL.md`, `docs/AUDITORIA-NODICO.md` y `docs/DEPLOY.md`.
3. Revisa qué skills tienes disponibles y usa las que apliquen. Al terminar, corre `/security-review` sobre el diff.
4. **Verifica primero que lo que está en `prueba.nodico.com.mx` corresponde al código local.** El despliegue a Hostinger tiene un fallo conocido de assets desincronizados (OPS-02) y algunos síntomas reportados podrían venir de un build viejo, no del código. Compara el `manifest.json` publicado contra el local antes de diagnosticar nada.

Trabaja por fases, en este orden. Commits pequeños en español. **No toques `/dashboard`, `/portal` ni el módulo de autenticación** salvo lo que se indica en el punto 4.2.

---

# FASE 1 — Lo que está roto hoy

## 1.1 · FE-16 · La Visión de /nosotros es ilegible por una clase de Tailwind inválida

`resources/js/Pages/Nosotros.vue` línea 108 tiene `bg-tinta/88`. **Tailwind 3.4 no genera esa clase**: el modificador de opacidad solo acepta valores de la escala (múltiplos de 5) o notación con corchetes. Verificado compilando: `bg-tinta/90` y `bg-tinta/[.88]` sí producen CSS, `bg-tinta/88` produce **nada**.

Consecuencia: ese `<div>` no tiene fondo, la foto se ve íntegra y el texto blanco encima es ilegible. Es el motivo exacto por el que la sección Visión no se entiende.

- Cámbialo a `bg-tinta/[.88]`.
- **Busca en todo el proyecto** cualquier otro modificador de opacidad fuera de escala y sin corchetes. Hoy solo hay este, pero añade una comprobación al proceso de build para que no vuelva a colarse — una clase inválida en Tailwind falla en silencio, sin error ni advertencia, y ese es el peor modo de fallo posible.
- Aprovecha y revisa **todos** los bloques de texto sobre imagen del sitio con un medidor de contraste real, no a ojo: portada de Nosotros, Visión, Beneficios de la portada, teaser de Salones, portada de Salones, portada de Comunidad, Coffee break. Ninguno debe bajar de 4.5:1 en el peor píxel del fondo. Si un velo plano no alcanza, usa degradado direccional como el del hero.

## 1.2 · FE-01 · El feed de Instagram no carga en producción

`InstagramSection.vue` embebe `https://www.instagram.com/{handle}/embed`. Ese endpoint de perfil completo **no está soportado ni documentado por Meta**: devuelve muro de inicio de sesión dentro del iframe. A los 8 segundos salta el respaldo, que solo muestra cuatro botones que dicen «Publicación 1…4».

- Los cuatro permalinks reales ya están en base de datos (`Ajuste::obtener('instagram_posts')`) y llegan al componente como prop `publicaciones`, pero nunca se embeben.
- Cambia a **un iframe por publicación** con `https://www.instagram.com/p/{shortcode}/embed/`, que sí es el endpoint público soportado. Extrae el shortcode del permalink.
- Reja de 1 columna en iPhone, 2 en iPad, 4 en desktop. `loading="lazy"` en todos menos el primero.
- Mantén un respaldo real: si un embed concreto no carga, esa celda muestra una tarjeta con el logo y enlace directo a esa publicación — no un botón genérico.
- En `docs/AUDITORIA-NODICO.md` deja anotado el camino a la Graph API (cuenta de empresa vinculada a Facebook + token de larga duración) para cuando quieran feed automático.

## 1.3 · IMG-01 · 8.2 MB muertos en cada despliegue

`public/hero-nodico.jpg` pesa 8.2 MB y no lo referencia ningún archivo. Vive en la raíz de `public/`, así que se sube por SFTP en cada deploy. Bórralo.

## 1.4 · FE-02 · ScrollReveal puede dejar secciones invisibles para siempre

`ScrollReveal.vue` cambió `IntersectionObserver` por escuchas de `scroll` y `resize`. Si un bloque entra en pantalla sin que ocurra ninguno de los dos —navegación de Inertia que aterriza a media página, contenido que crece al cargar imágenes, rotación gestionada solo por CSS— el bloque se queda en `opacity: 0` de forma permanente. Con `stagger > 0` es peor: los hijos reciben `opacity: 0` en línea y el padre queda visible, así que la sección se ve vacía.

- Vuelve a `IntersectionObserver`, que es la herramienta correcta.
- Añade tres redes de seguridad: recomprobar tras `load`, tras cada navegación de Inertia y en `orientationchange`; y un temporizador que revele todo a los 3 segundos pase lo que pase. **Nunca** debe existir un camino que deje contenido invisible.

## 1.5 · FE-03 · El carrusel se centra mal al cargar

`irA(destacado, false)` corre en `onMounted`, cuando las tarjetas aún no tienen su ancho final porque no han cargado fuentes ni imágenes. Nodo Pro arranca descuadrada. Recalcula tras `document.fonts.ready` y engancha un `ResizeObserver` a la pista.

## 1.6 · PERF-01 · La fachada del video no retrasa nada

El iframe de YouTube se carga con `IntersectionObserver` y `rootMargin: 200px`, pero el hero ya está en pantalla al abrir: el observador dispara de inmediato y el reproductor (~600 KB) entra en la carga inicial compitiendo con las fuentes y el póster.

Retrásalo hasta después del primer pintado: espera a `load` más un margen, o a que el usuario haga scroll o interactúe. El póster se sostiene solo durante ese par de segundos.

---

# FASE 2 — Peso y rendimiento

Aquí está casi todo el tiempo de carga en un iPhone con datos móviles, que es el escenario real del público objetivo.

| ID | Qué hacer |
|---|---|
| **IMG-02** | 4.2 MB de fuentes en OTF/TTF sin WOFF2 (6 pesos de Carmen Sans a ~650 KB c/u + 286 KB de GT Eesti). Convierte a **WOFF2** y subsetea a latín con acentos y signos en español: baja a ~400-600 KB. Añade `<link rel="preload">` para los dos pesos del primer pintado. Conserva los OTF originales fuera de `public/`. |
| **IMG-03** | Cero `srcset` en todo el sitio: un iPhone descarga las imágenes de 1920 px. Genera tres anchos (640 / 1280 / 1920) por imagen y declara `srcset` con `sizes`. Solo en la portada ahorra ~1 MB en móvil. |
| **IMG-07** | 22 SVG exportados de Illustrator, 9-47 KB cada uno, ~430 KB en total, para dibujos de una sola tinta. Pásalos por SVGO (quedan en 2-5 KB), conviértelos a `currentColor` y métELOS en línea para que tomen el color del tema. |
| **FE-08** | Muchas imágenes declaran `width="1000" height="750"` cuando el archivo real es 1920×1079, 1920×1280 o 1079×1920. Los atributos existen pero mienten, así que no evitan el salto de layout. Genera los valores a partir de las dimensiones reales y añade una verificación al build. |
| **FE-07** | El póster del hero declara medidas verticales (1079×1920) pero se pinta a pantalla completa horizontal. Como es un fondo absoluto que siempre llena el contenedor, quita los atributos y fija la caja por CSS. |
| **PERF-02** | `lenis` mantiene un `requestAnimationFrame` permanente en todas las páginas públicas. Pausa el bucle cuando la pestaña no esté visible y cuando el scroll esté quieto. |
| **PERF-03** | El mapa de Google se embebe en las cinco páginas. Conviértelo en fachada: imagen estática + clic para cargar el iframe. De paso evita la cookie de Google en cada visita. |
| **PERF-04** | `vue-sonner` y su CSS se cargan en el layout para un solo formulario. Cárgalo diferido junto al formulario, o quítalo: el formulario ya muestra mensaje en línea. |
| **IMG-06** | `favicon.ico` pesa **0 bytes** y no hay `apple-touch-icon`. Genera el juego completo desde el logo: `favicon.svg`, `favicon.ico`, `apple-touch-icon.png` de 180 px y `site.webmanifest` con el amarillo de marca como color de tema. |
| **IMG-09** | `logo-nodico-blanco.png` es un PNG de paleta de 1920×637 usado a menos de 50 px de alto. Conviértelo a SVG — los vectoriales originales están en `nodico/PAGINA WEB NÓDICO/`. |
| **IMG-04** | Borra los assets huérfanos: `public/icons/` (10 PNG), `nosotros-fondo.png` (568 KB), `fondo-amarillo.webp`, las tres versiones `.webp` de los logos de aliados (se usan las `.png`), y `public/logo-nodico-blanco.png` (duplicado). Añade al build una comprobación de assets no referenciados. |
| **IMG-08** | Tres fotos del directorio no dan la talla: `salabtun` (512×511, 7 KB, se ve comprimida), `dir-zentto.jpg` (650×441 en reja cuadrada, se recorta mal), `dir-kinimitas.png` (PNG de paleta 1920×1920 para mostrarse a 300). Recorta a cuadrado y exporta a WebP 600 px. Si no hay originales mejores, anótalo como pendiente. |
| **FE-15** | Borra ~120 líneas de CSS muerto en `app.css`: `.dot-pattern`, `.reveal*`, `.stagger*`, `.magnetic`, `.card-3d`, `.noise`, `.input-underline*` y todo el bloque de cursor personalizado (`#cursor-outer`, `.cursor-zone`), que además aplica `cursor: none !important` y rompería la accesibilidad si alguien lo activara. |

---

# FASE 3 — Metadatos, accesibilidad y datos

## Metadatos

| ID | Qué hacer |
|---|---|
| **SEO-01** | No hay una sola etiqueta `og:` ni `twitter:` en el proyecto — el sitio de Odoo sí las tenía. Hoy compartir cualquier URL en WhatsApp o Instagram muestra la URL pelada, justo el canal por donde llega el público joven. Crea un componente `<Meta>` que reciba título, descripción e imagen y emita el juego completo. Prepara una imagen social de 1200×630 por página. |
| **SEO-02** | Ninguna página declara canónica. Emítela desde el Blade con `url()->current()` normalizada, y **decide de una vez si producción lleva `www` o no**. |
| **SEO-03** | Sin datos estructurados, teniendo todos los datos ya capturados en `config/nodico.php` y en la tabla de planes. Emite `application/ld+json`: `LocalBusiness` en todas las páginas, `Offer` por plan en membresías, `Event` en actividades. |
| **SEO-04** | El sufijo «— Nódico» se repite a mano en cada página y el Blade cae a `config('app.name')`: dos fuentes de verdad. Unifica con `Inertia::share` y una plantilla de título. |
| **SEO-05** | No hay `sitemap.xml`. Genera la ruta con las cinco públicas más las legales, y anúnciala desde el `robots.txt` de producción. |

## Accesibilidad

| ID | Qué hacer |
|---|---|
| **A11Y-01 / FE-10** | El menú móvil es un `<div>` sin `role="dialog"` ni `aria-modal`: con Tab se sigue navegando el fondo y el lector de pantalla anuncia la página completa. Además `@click.self` sobre un contenedor flex que llena la pantalla hace que un toque en cualquier hueco lo cierre. Conviértelo en diálogo real, atrapa el foco, marca el resto como `inert`, devuelve el foco al botón al cerrar, y limita el cierre por toque al fondo real. |
| **A11Y-02** | La pista del carrusel tiene `tabindex="0"` y captura flecha izquierda/derecha con `.prevent`: quien navegue con teclado ya no puede desplazar la página, y nada indica que ese contenedor esté enfocado. Dale estado visible al foco y deja las flechas verticales libres. |
| **A11Y-03** | Los puntos usan `aria-current="true"`, que no es el valor adecuado, y no hay región `aria-live`: con autoplay, un lector de pantalla no se entera del cambio. Pasa a patrón de pestañas con `aria-selected` y añade `aria-live="polite"` que anuncie «Membresía 3 de 4: Nodo Pro». |
| **A11Y-04** | Contraste insuficiente: etiquetas técnicas a `text-dark/30` y `text-white/35`, `text-dark/70` sobre el amarillo `#FFE124` (bloque day-pass y cierre del footer), `text-white/45` en el aviso de facturación. Sube las opacidades bajas a 55-60 % y verifica cada combinación contra su fondo real. El amarillo de marca es muy luminoso: encima, el texto necesita estar casi a opacidad completa. |
| **A11Y-05** | Los números «01…06» de servicios y valores se leen en voz alta sin aportar nada. Márcalos `aria-hidden`, como ya se hace bien en beneficios. |
| **A11Y-06** | El honeypot usa `absolute left-[-9999px]` dentro de un `<form>` sin `position: relative`: se posiciona contra un ancestro incierto y podría aparecer en pantalla. Fija `position: relative` en el formulario. |
| **FE-04** | El autoplay del carrusel se detiene con `@focusin` pero no existe `@focusout`: quien entre con teclado lo deja congelado. Y tras un swipe táctil se reanuda de inmediato, arrastrando la tarjeta que el usuario acababa de elegir. Añade `@focusout`, y tras cualquier interacción espera varios segundos — o apaga el autoplay, que en una lista de precios se agradece más. |
| **FE-05** | La detección de iPhone es por `navigator.userAgent`: el iPad moderno se anuncia como Mac y recibe el video de fondo aunque comparta las mismas restricciones. Decide por capacidad: `matchMedia('(hover: none) and (pointer: coarse)')`, o intenta reproducir y cae al póster si el navegador lo rechaza. |
| **FE-06** | El iframe del hero usa `h-[100vh]` mientras la sección usa `min-h-[100svh]`. En iOS `100vh` incluye la barra de direcciones: el video se recorta de más y en horizontal el hero desborda. Unifica en `svh` y baja el `scale-[1.35]`, que está compensando justo ese desajuste. |
| **FE-11** | `SiteHeader` y `HeroVideo` escriben ambos `document.body.style.overflow`. Si se abre el modal y luego el menú, el primero en cerrar destraba el scroll del otro. Crea un composable con contador de bloqueos. |
| **FE-12** | Todo el sitio público tipa como `any` (`planes: any[]`, `salon: any`). Se pierde la verificación justo donde más ayudaría: los nombres de campo del seeder. Un `periodo_label` mal escrito no da error, solo desaparece de la pantalla. Declara interfaces `Plan`, `Salon` y `Evento` a partir de los modelos. |
| **FE-13** | `tarjetas.value[i]` nunca se vacía: si la lista de planes cambia de tamaño quedan referencias a nodos desmontados. |
| **FE-14** | El umbral antiarrastre de 6 px es menor que el temblor normal de un dedo, así que algunos toques al CTA se pierden en iPhone. Súbelo a 10-12 px y aplícalo solo con puntero de ratón. |

## Datos y backend

| ID | Qué hacer |
|---|---|
| **CNT-02** | El directorio de emprendedores (Ahimsa Daram, Zentto, SaboReli, Kinimitas) está codificado dentro de `Comunidad.vue`: cambiar uno obliga a recompilar y desplegar, cosa que nadie del equipo de contenido puede hacer. Llévalo a base de datos como se hizo con planes y salones, administrable desde el panel. **Lo mismo con el «Emprendedor de la semana»**, que por definición cambia cada semana. |
| **CNT-01** | «Nuestro contenido más reciente» va envuelta en `v-if="eventos.length"`: con la tabla vacía —el estado actual— la sección no existe y la página salta de la portada a los talleres. El original tenía además un «Ver todo» que no se migró. Muestra un estado vacío con sentido, o alimenta la sección desde el mismo calendario de Luma que ya está embebido, para tener una sola fuente. |
| **CNT-03** | El «Saber Más…» del emprendedor de la semana se perdió en la migración: la ficha no lleva a ninguna parte. Define destino (Instagram del negocio, ficha del directorio o nota) y añádelo al modelo. |
| **BE-01** | `WelcomeController@index` envía la prop `eventos` que `Welcome.vue` no declara ni usa: una consulta en cada carga de la página más visitada, para nada. Quítala, o úsala. |
| **BE-02** | `throttle:5,1` cuenta por IP, y todos los miembros del coworking salen por la misma: cinco envíos en un minuto desde el propio espacio bloquean a los demás. Combina la clave con el correo del formulario, sube el margen y devuelve un mensaje claro al alcanzarlo. |
| **BE-03** | El teléfono se guarda tal cual se escriba. Normaliza a E.164 al guardar, conservando lo que escribió la persona en un campo aparte. |
| **BE-04** | El aviso de privacidad y los términos son arreglos PHP dentro de `WelcomeController`, marcados `'provisional' => true` pero **publicados como definitivos**. Muévelos a Markdown o base de datos y avisa a Nódico de que hoy hay texto legal sin validar en producción. |
| **BE-05** | `Ajuste::obtener` traga cualquier `Throwable`: un fallo real de base de datos deja la sección vacía sin que nadie se entere. Registra en el log salvo en consola. |
| **IMG-05** | Las cuatro fotos de plan (425 KB) están sembradas en `Plane.imagen` pero el carrusel nunca las pinta. Decide: úsalas como cabecera de cada tarjeta —daría mucha más vida al carrusel— o quítalas del seeder y borra los archivos. |

---

# FASE 4 — Portada

Nueve cambios. Lo que no se menciona **no se toca**.

### 4.1 · Hero — sin cambios

Queda exactamente igual (salvo las correcciones técnicas de FE-06, FE-07 y PERF-01, que no alteran su aspecto).

### 4.2 · Navbar — centrado, con acceso y registro

- **Tres zonas:** logo a la izquierda, navegación **centrada**, acciones a la derecha. Úsalo con `grid-cols-[auto_1fr_auto]`, **no** con posicionamiento absoluto — a 1024 px el nav absoluto se encima con el logo.
- **Acciones:** «Iniciar sesión» como enlace de texto y «Registrarse» como botón amarillo. Las props `canLogin` y `canRegister` ya se comparten desde el controlador; las rutas `login` y `register` ya existen, con sus páginas en `Pages/Auth/`.
- **Con sesión iniciada** (`auth.user` ya viene compartido): en vez de los dos anteriores, un menú con el nombre de la persona → «Mi portal» (o «Dashboard» si es admin) y «Cerrar sesión».
- **En móvil:** dentro del menú a pantalla completa, debajo de los enlaces, con el mismo peso que «Únete a Nódico».
- Cuida que a 1024-1280 px no se aprieten los cinco enlaces con las dos acciones: si no caben, baja el punto de corte del menú hamburguesa.

### 4.3 · Servicios — rehacer por completo

Hoy son seis filas idénticas en un panel blanco dividido por líneas: correcto, pero plano y sin jerarquía.

**Nueva dirección: mosaico asimétrico.** Dos servicios protagonistas ocupan celdas grandes con foto real del espacio de fondo y velo oscuro; los otros cuatro van compactos con icono sobre fondo claro. En iPad pasa a dos columnas, en iPhone a una sola apilada.

- Elige como protagonistas los dos con más peso comercial: **espacio colaborativo** y **sala profesional de creación de contenido**.
- Cada servicio necesita **una línea de descripción**, no solo el título — hoy son títulos sueltos y por eso se sienten flojos. Escríbelas tú, cortas y concretas, y márcalas para que Nódico las revise.
- Los iconos, ya optimizados e inline por IMG-07, toman el color del tema: oscuros sobre las celdas claras, amarillos sobre las oscuras.

### 4.4 · Beneficios — rehacer por completo

Hoy es una lista numerada a dos columnas sobre foto con velo. Se lee como un pie de página.

**Nueva dirección: paneles expansibles.** En desktop, cinco paneles verticales lado a lado; el activo se ensancha y revela foto, título y descripción, los demás se comprimen mostrando solo el título en vertical. Se activa al pasar el cursor **y** con teclado.

- **En táctil el hover no existe**: en iPad e iPhone conviértelo en tarjetas apiladas con foto, cada una con un color de acento distinto tomado de la paleta de planes (`plan.flex`, `plan.pro`, `plan.daypass`, `plan.match`). Un solo componente, dos comportamientos según `pointer: coarse`.
- Con `prefers-reduced-motion: reduce`, todos los paneles abiertos y sin transición.
- Cada beneficio necesita también una línea de descripción.

### 4.5 · Membresías — sin cambios

El carrusel queda igual (salvo FE-03, FE-04, A11Y-02 y A11Y-03, que no cambian su aspecto).

### 4.6 · Day-pass — más animación

Es el gancho comercial más fuerte de la portada y hoy está quieto.

- **Sello circular giratorio** superpuesto en una esquina de la foto, con el texto siguiendo el círculo: «DAY-PASS GRATUITO · INTERIOR DEL ESTADO ·». Usa la animación `spin-slow` que ya está en `tailwind.config.js` y nunca se usó.
- **Parallax suave** en la foto al hacer scroll: se desplaza más lento que el resto de la sección. Sutil, unos 40-60 px de recorrido total.
- El bloque «Tu day-pass siempre es gratuito» entra como **sello estampado**: escala desde 1.15 con un giro mínimo y asienta con el easing `salida` que ya está definido. Que llegue después del titular, no a la vez.
- El CTA «Contáctanos para más informes» con la flecha desplazándose al hover, como el resto del sistema.
- **Todo esto se apaga por completo con `prefers-reduced-motion: reduce`.** El sello deja de girar, el parallax se desactiva, el bloque aparece sin animación.

### 4.7 · Salones — cambiar la imagen

`teaser-salones.png` mide **426×256 px** y se está escalando cuatro veces como fondo a sangre (FE-09). Sustitúyelo por **`salon-yucatan-emprende-2.webp`** (1920×1440) — no uses `salon-yucatan-emprende-1.webp`, que ya es la portada de `/eventos` y se repetiría. Borra `teaser-salones.png`.

### 4.8 · Instagram — quitar el subtítulo y el handle

En la cabecera de `InstagramSection.vue`, elimina:

- el párrafo «Talleres, miembros nuevos y la vida diaria del espacio, día con día.»
- el botón «@nodicomx» que enlaza al perfil

Queda solo la etiqueta técnica «Instagram» y el título «Lo que pasa en Nódico», sobre la reja de publicaciones ya corregida en 1.2. Ajusta el espaciado: sin esos dos elementos la cabecera queda desbalanceada tal cual está.

> Si al ver el resultado consideras que la sección necesita algún acceso al perfil, ponlo discreto **debajo** de la reja y **pregúntame antes** de añadirlo.

### 4.9 · Contacto — mejor distribución

Hoy son dos columnas: a la izquierda los datos apilados más un mapa alto, a la derecha el formulario en tarjeta fija. La izquierda queda larguísima y el conjunto desequilibrado.

**Nueva distribución en tres zonas:**

1. **Franja de datos arriba**, horizontal, en cuatro columnas iguales: correo, teléfono, dirección, horarios. Cada uno con su icono, y la dirección con su enlace a Maps. En iPhone, dos por fila.
2. **Debajo, dos columnas a la misma altura:** el formulario ocupando ~60 % y el mapa ~40 %, ambos en tarjetas de altura idéntica. Se acabó el mapa suelto colgando y la tarjeta fija.
3. **En iPhone:** datos en 2×2, luego el formulario, luego el mapa al final.

El mapa entra como fachada (PERF-03). Conserva todo el comportamiento actual del formulario —etiquetas flotantes, validación en vivo, honeypot, estados de envío—, que está bien resuelto.

---

# FASE 5 — Las otras cuatro páginas

Revisa `/nosotros`, `/membresias`, `/eventos` y `/actividades` **página por página, en el navegador**, no solo leyendo el código:

- **Todo texto se tiene que poder leer.** El caso de la Visión (1.1) es el ejemplo, pero revisa cada bloque sobre imagen con medidor de contraste.
- **Todo texto se tiene que entender.** Donde el texto venga heredado de Odoo y sea confuso, redundante o esté cortado, arréglalo — y anota en `docs/AUDITORIA-NODICO.md` qué cambiaste para que Nódico lo valide.
- Aplica el sistema de diseño de forma consistente: si tras los cambios de la portada alguna sección de estas páginas se queda anticuada respecto al resto, actualízala.
- Ninguna página debe tener scroll horizontal en ningún ancho.

---

# FASE 6 — Verificación

Antes de desplegar:

1. `npm run build` y `php artisan test` sin errores. **Añade pruebas** para lo que arreglaste: que `/nosotros` renderice, que el seeder de Instagram devuelva permalinks válidos, que el contacto respete el límite de envíos.
2. Consola del navegador: **cero errores y cero advertencias** en las cinco páginas.
3. Recorre el sitio completo **solo con teclado**: todo alcanzable, foco siempre visible, el menú móvil y el modal del video atrapan el foco correctamente.
4. Prueba en **375 / 393 / 430 / 744 / 1024 / 1366 px** y en **horizontal de iPhone**, que es donde se rompen los heroes a pantalla completa. Toma capturas, revísalas tú y corrige antes de enseñármelas.
5. Lighthouse en móvil: apunta a 90+ en Rendimiento y Accesibilidad. Reporta lo que quede por debajo y por qué.
6. Verifica el peso total de la portada antes y después. Di la cifra concreta.
7. Corre `/security-review` sobre el diff.
8. Actualiza `docs/AUDITORIA-NODICO.md`: qué hallazgo se cerró, cómo, y cuáles quedaron abiertos y por qué.

---

# FASE 7 — Despliegue a prueba.nodico.com.mx

Sigue `docs/DEPLOY.md`. Recordatorios críticos de ese servidor (Hostinger, hosting compartido):

- **No tiene Node**: compila en local con `npm run build` antes de subir.
- Los assets van a **dos rutas** que deben quedar sincronizadas: `public_html/build/` y `public_html/public/build/`. Si solo actualizas una, el sitio sirve assets rotos — ya pasó una vez.
- Los estáticos nuevos (imágenes, fuentes WOFF2, favicons, SVG) van a la **raíz** de `public_html/`, no dentro de `public_html/public/`.
- El CLI corre PHP 8.2 y el sitio PHP-FPM 8.3: que `php artisan` termine bien **no** confirma que la web funcione.
- Nunca sobrescribas el `.env` del servidor.

Aprovecha para cerrar los dos hallazgos de despliegue:

- **OPS-01:** que `deploy_prueba.py` se niegue a desplegar con el árbol de git sucio, y que escriba el hash del commit dentro del build para poder verificar después qué está publicado.
- **OPS-02:** que al final del despliegue compare los dos `manifest.json` y haga una petición real con `curl` a uno de los assets publicados.

```bash
npm run build
python deploy_prueba.py
```

Verificación posterior, obligatoria:

```bash
for r in / /nosotros /membresias /eventos /actividades /aviso-de-privacidad /terminos; do
  echo "$r -> $(curl -s -o /dev/null -w '%{http_code}' https://prueba.nodico.com.mx$r)"
done
curl -s https://prueba.nodico.com.mx/robots.txt   # debe decir "Disallow: /"
```

Después abre el sitio en el navegador y confirma **en el servidor, no en local**: que la Visión ya se lee, que los embeds de Instagram cargan de verdad, que el video del hero arranca, que el carrusel centra Nodo Pro, que el formulario envía, y que el mapa carga al pulsarlo. Mándame el resumen con las cifras de peso y de Lighthouse.

---

## FORMA DE TRABAJAR

- Muéstrame tu plan antes de empezar y espera mi visto bueno. Después ya no me consultes cada detalle.
- Termina cada fase, enséñame el resultado, y hasta que lo apruebe sigue con la siguiente. La Fase 4 muéstramela sección por sección.
- Si algo del diseño no te convence al verlo renderizado, arréglalo sin preguntar. Tienes libertad creativa dentro del sistema de marca.
- Si un hallazgo resulta más caro de lo que vale o descubres que ya no aplica, dilo y justifícalo en vez de forzarlo.
- No inventes contenido: si falta una foto, un dato o un permalink, ponlo en una lista de pendientes y pregúntame.
- Todo el texto de la interfaz en español, con acentos correctos.
