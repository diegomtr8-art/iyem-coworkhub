# Auditoría del sitio original nodico.com.mx

> Fecha: 2026-08-28 · Origen: `https://www.nodico.com.mx/` (Odoo) · Destino: este proyecto Laravel.
> Inventario sección → texto → imagen local. Todas las imágenes viven en `public/img/nodico/`.
> **No queda ninguna URL apuntando a nodico.com.mx.**

## Mapa de rutas

| Odoo | Este proyecto | Página Inertia |
|---|---|---|
| `/` | `/` | `Welcome.vue` |
| `/nosotros` | `/nosotros` | `Nosotros.vue` |
| `/membresias` | `/membresias` | `Membresias.vue` |
| `/salones` | `/eventos` (ruta `eventos`) | `Salones.vue` |
| `/comunidad` | `/actividades` (ruta `actividades`) | `Comunidad.vue` |

Navegación original: Inicio · Nosotros · Membresías · Eventos(`/salones`) · Actividades(`/comunidad`),
más iconos de carrito y wishlist de Odoo — **omitidos**, no hay e-commerce.

Redes sociales reales (resueltas siguiendo los redirects de `/website/social/*`):

- Instagram `https://www.instagram.com/nodicomx`
- Facebook `https://www.facebook.com/nodicomx`
- LinkedIn `https://www.linkedin.com/in/nodico-club-de-emprendedores-316513377/`

---

## `/` — Inicio → `Welcome.vue`

| Sección | Texto | Imagen local |
|---|---|---|
| Hero | «Bienvenidos al lugar / Donde el trabajo es un pretexto para crear» + CTA «Conocer más →» → `/nosotros` | `hero-inicio.webp`, fondo `fondo-amarillo.webp` |
| Servicios | Espacio colaborativo de trabajo · Wifi con 200 MB de velocidad · Sala profesional de creación de contenido · Servicios de recepción de paquetería · Hasta 5 invitados gratuitos al mes por membresía · Café y agua durante todo el día | `icono-espacio-colaborativo.svg`, `icono-wifi.svg`, `icono-sala-contenido.svg`, `icono-paqueteria.svg`, `icono-invitados.svg`, `icono-cafe-agua.svg` |
| Beneficios adicionales | Descuentos exclusivos en Tienda Herencia Viva · Directorio de servicios y productos de miembros Nódico · Acceso preferente a eventos, talleres y capacitaciones · Conexión directa con el ecosistema emprendedor local y nacional · Espacio pet friendly | `icono-descuentos.svg`, `icono-directorio.svg`, `icono-eventos-talleres.svg`, `icono-ecosistema.svg`, `icono-pet-friendly.svg` |
| Elige tu plan ideal | Intro + 4 tarjetas: Nódico Flex, Nodo Pro, Day-Pass, Nodo Match («Saber más...» → `/membresias`) | `plan-nodico-flex.webp`, `plan-nodo-pro.webp`, `plan-day-pass.webp`, `plan-nodo-match.webp` |
| Banner day-pass | «¿Eres emprendedor o artesano del Interior del Estado? ¡Tu daypass siempre es gratuito!» + «Contáctanos para más informes» | `daypass-emprendedor.webp` |
| Aliados | «¿Quieres conocer más de Nódico?» → IYEM `https://iyem.yucatan.gob.mx/`, Herencia Viva `http://www.herenciaviva.com`, CANIETI `https://canieti.org` | `logo-iyem.webp`, `logo-herencia-viva.webp`, `logo-canieti.webp` |
| Teaser salones | «Conoce nuestros Salones para eventos» + ficha técnica | `teaser-salones.png` |
| Hablemos | Formulario: Nombre, Teléfono, E-mail, Su empresa, Asunto, Comentarios | — |

## `/nosotros` → `Nosotros.vue`

| Sección | Texto | Imagen local |
|---|---|---|
| Hero | «¿Quiénes somos?» — «Más allá de un espacio físico, Nódico es una comunidad profesional donde se fomenta la colaboración la vinculación estratégica y el desarrollo de habilidades a través de experiencias compartidas, eventos y formación continua.» + CTA «Conocer más» → `/actividades` | `nosotros-hero.webp`, fondo `nosotros-fondo.png` |
| Nuestra Misión | «Ser el espacio donde los emprendedores encuentran las herramientas, conexiones y experiencias necesarias para transformar sus ideas en proyectos de impacto. / En NÓDICO impulsamos la creatividad, la colaboración y la innovación mediante espacios funcionales, contenido de valor y una comunidad vibrante que reta el pensamiento y promueve el crecimiento.» | `mision.webp` |
| Nuestra Visión | «Consolidarnos como el espacio referente en el sureste de México para el desarrollo de la creatividad, el emprendimiento y la innovación, reconocido por ser el punto de encuentro donde convergen las nuevas generaciones de creadores, emprendedores y agentes de cambio.» | `vision.webp` |
| Nuestros Valores | Creatividad · Colaboración · Innovación · Diversidad e inclusión · Democratización del acceso a espacios de calidad · Comunidad | `valor-creatividad.svg`, `valor-colaboracion.svg`, `valor-innovacion.svg`, `valor-diversidad-inclusion.svg`, `valor-democratizacion.svg`, `valor-comunidad.svg` |
| Hablemos | Formulario | — |

## `/membresias` → `Membresias.vue`

Encabezado «Precios competitivos». Sin imágenes en el original. Datos ahora en BD (`planes`).

| Plan | Precio | Periodo | Beneficios (textual del original) |
|---|---|---|---|
| Day-Pass | $79.00 | por 1 día | Acceso a espacio colaborativo · 1 hora en sala de creación de contenido · Agua y café durante su estancia |
| Nódico Flex | $249.00 | por 4 días | 4 días acceso al coworking · 4 horas en sala de creación de contenido (1 por día) · Agua y café durante su estancia |
| Nodo Pro | $599.00 | al mes | Acceso ilimitado al coworking · 10 horas al mes en oficinas privadas y sala de juntas (2 horas por día) · 10 horas al mes sala de creación de contenido · 4 horas Asesor IYEM (1 hora por día) · 20% Capacitaciones IYEM · Acceso libre a eventos de Cultura emprendedora · Acceso directo a programas del Instituto Yucateco de Emprendedores · Agua y café durante su estancia |
| Nodo Match (2 pax) | $799.00 | por 1 mes | Acceso completo al coworking · 20 horas al mes en oficinas privadas y sala de juntas (2 horas por día) · 15 horas al mes en sala de creación de contenido (previa reserva en la aplicación) · Agua y café durante tu estancia · Acceso directo a programas del Instituto Yucateco de Emprendedores · Acceso libre a eventos de Cultura emprendedora |

Checkout Stripe (sembrado en `planes.stripe_url`):

- Day-Pass `https://buy.stripe.com/00waER7JU4Lp65v0gb6Zy05`
- Nódico Flex `https://buy.stripe.com/6oUdR36FQ0v951r4wr6Zy04`
- Nodo Pro `https://buy.stripe.com/6oU8wJggq91FeC1e716Zy03`
- Nodo Match `https://buy.stripe.com/28EcMZc0agu73Xn6Ez6Zy02`

## `/salones` → `Salones.vue`

Hero «Conoce nuestros Salones para eventos» sobre `fondo-amarillo.webp`.

Ambas salas comparten descripción e «Incluye»: Proyector · Sistema de sonido · Servicio de internet ·
Sillas · Mesas · Manteles · Base de micrófono · Extensiones · Adaptador HDMI · Mesa de registro · Pódium.

| Sala | Medidas | Costo/hora | Capacidad | Herradura | Mesas de trabajo | Escuela | Auditorio | Imagen |
|---|---|---|---|---|---|---|---|---|
| Yucatán Emprende 1 | 15x14 m | $600 mxn | 120 | 45 | 70 | 54 | 120 | `salon-yucatan-emprende-1.webp` |
| Yucatán Emprende 2 | 15x14 m | $600 mxn | 120 | 45 | 70 | 54 | 120 | `salon-yucatan-emprende-2.webp` |

Nota Coffee Break: 25 PAX $45.00 MXN P/P · a partir de 100 PAX $35.00 MXN P/P. Imagen extra: `salon-detalle.webp`.

## `/comunidad` → `Comunidad.vue`

| Sección | Texto | Imagen local |
|---|---|---|
| Contenido reciente | «Nuestro contenido más reciente» / «Conozca las novedades de nuestra empresa» | — (datos de `eventos`) |
| Talleres del mes | Párrafo + calendario Luma `https://luma.com/embed/calendar/cal-ZE3dbDW6bLs4v7j/events?lt=dark` | — |
| Emprendedor de la semana | Salabtún, sal artesanal de las charcas mayas de Celestún | `emprendedor-semana-salabtun.webp` |
| Directorio | Ahimsa Daram · Zentto · SaboReli · Kinimitas | `dir-ahimsa-daram.jpg`, `dir-zentto.jpg`, `dir-saboreli.webp`, `dir-kinimitas.png` |
| Teaser salones | «Conoce nuestros Salones para eventos» → `/eventos` | `teaser-salones.png` |
| Hablemos | Formulario | — |

Instagram del directorio: `ahimsadaram`, `zentto.mid`, `saborelimx`, `kinimitas`. Fondo: `comunidad-fondo.webp`.

---

# Discrepancias y decisiones

Marcadas **[RESUELTA]** (aplicada) o **[A VALIDAR]** (requiere confirmación de Diego).

### 1. `/salones` tiene TRES tarjetas, dos con el mismo nombre — [RESUELTA / A VALIDAR]

El original renderiza «Yucatán Emprende 1» (capacidad 120 / auditorio 120), otra vez
«Yucatán Emprende 1» (capacidad 150 / auditorio 150) y «Yucatán Emprende 2» (120 / 120).

**Decisión aplicada:** se elimina la tarjeta de 150 pax por considerarse duplicado editorial;
quedan YE1 = 120 pax y YE2 = 120 pax.

**A validar:** si la de 150 pax es una sala real distinta, hay que darla de alta en `espacios`.

### 2. Placeholders sin llenar en el teaser de salones — [RESUELTA]

En `/` y `/comunidad` la ficha del teaser dice literalmente «Capacidad: 0000 pax» y
«Auditorio: 0000 pax», y sus otros números no coinciden con `/salones`
(escuela 48 vs 54, mesas de trabajo 65 vs 70, herradura 35 vs 45).

**Decisión aplicada:** el teaser usa los valores reales de `/salones` y se eliminan los `0000`.

### 3. Snippet dinámico roto en `/comunidad` — [RESUELTA]

La sección «Nuestro contenido más reciente» muestra en producción el error de Odoo:
*«Su snippet dinámico aparecerá aquí... Este mensaje aparece porque no proporcionó ni el filtro ni la plantilla a usar.»*

**Decisión aplicada:** se sustituye por los eventos reales de la BD (props `proximos` / `pasados`).

### 4. Calendario Luma duplicado — [RESUELTA]

El original inserta dos `<iframe>` idénticos del mismo calendario. Se deja **uno solo**, responsivo.

### 5. «Emprendedor de la semana» sin destino — [RESUELTA / A VALIDAR]

El botón «Saber Más...» no apunta a ninguna URL en el original, y la única imagen asociable
(`emprendedor-semana-salabtun.webp`) pesa apenas 7.8 KB, por lo que se verá de baja resolución.

**Decisión aplicada:** el botón enlaza a la propia sección; se usa la imagen disponible.

**A validar:** ¿hay una URL destino (Instagram de Salabtún) y una imagen en mejor resolución?

### 6. Erratas del original — [RESUELTA]

- «Coffe Break» → «Coffee Break».
- Nodo Pro: «un lugar **d** trabajo constante» → «un lugar **de** trabajo constante».
- El plan se escribe «NODICO FLEX» en `/` y «Nódico Flex» en `/membresias`;
  se unifica a **«Nódico Flex»** (con acento).

### 7. Meta descriptions vacías y `noindex` en el home — [RESUELTA]

El original trae `noindex` en `/` y descripciones genéricas. Se escriben descripciones propias por
página. En **staging el `noindex` se mantiene a propósito** (ver `docs/DEPLOY.md`).

### 8. Bug propio del proyecto detectado durante la auditoría — [RESUELTA]

`resources/views/app.blade.php` declaraba `@vite([... "resources/js/Pages/{$page['component']}.vue"])`,
pero `vite.config.js` sólo registra `resources/js/app.js` como entrada. En dev funciona, pero en
producción el manifest no contiene esas páginas y Laravel lanza
`Unable to locate file in Vite manifest`. Inertia ya carga las páginas vía `import.meta.glob`,
así que se elimina esa segunda entrada.

---

# Cambios de texto de la Fase 5 — a validar por Nódico

Al revisar las cuatro páginas interiores en el navegador aparecieron textos
heredados de Odoo que eran **redundantes, vagos o directamente repetidos**. Se
corrigieron; aquí queda el registro para que Nódico los valide.

## Redundancia literal en `/eventos`

Las dos salas traían **exactamente el mismo párrafo de 60 palabras** y **la misma
lista de once elementos**, repetidos íntegros en cada ficha. Leer dos veces lo
mismo en la misma pantalla no aporta nada.

- La descripción pasa a un único párrafo de entrada, antes de las dos salas.
- «Incluye» se muestra una sola vez, como «Ambas salas incluyen».
- Cada ficha conserva solo lo suyo: foto, nombre y datos técnicos.

Si algún día las salas dejan de ser idénticas, hay que volver a mostrar
descripción y equipamiento por sala. Está anotado en el código.

## Puntos finales sobrantes

Los elementos de «Incluye» venían como `Proyector.`, `Sillas.`, `Mesas.`… En una
lista de una sola palabra el punto final sobra. Retirados.

## Descripciones de plan que decían lo mismo dos veces

| Plan | Antes | Ahora |
|---|---|---|
| Nódico Flex | «Pensada para jóvenes emprendedores, estudiantes o personas que solo necesitan entrar al espacio de cowork por unas horas y tener acceso a la comunidad emprendedora. **Es una opción accesible para quienes están empezando y quieren conectar, trabajar un rato o explorar el ecosistema.**» — dos frases para la misma idea | «Para quien está empezando y necesita el espacio unas horas: entrar, trabajar un rato, conectar con la comunidad y explorar el ecosistema emprendedor.» |
| Nodo Match | «Pensada para jóvenes emprendedores, ofrece una variedad más amplia de servicios y beneficios diseñados para impulsar el desarrollo de proyectos innovadores y fomentar la colaboración en un entorno dinámico.» — relleno que **omitía su único diferenciador: que es para dos personas** | «La misma libertad que Nodo Pro, pero para dos personas: pensada para socios o duplas que trabajan juntos todos los días.» |

## Valor con nombre de tres líneas en `/nosotros`

«Democratización del acceso a espacios de calidad» ocupaba tres renglones como
título de tarjeta. Se acorta a **«Democratización del acceso»** y el resto pasa al
cuerpo: *«Un espacio de calidad no debería ser un privilegio. Por eso los precios
son los que son.»*

## Texto nuevo redactado en esta fase

El original no tenía descripciones en servicios, beneficios ni valores: eran
títulos sueltos, y por eso esas secciones se sentían flojas. **Las redacté yo y
están pendientes de validación**:

- 6 descripciones de servicio (portada)
- 5 descripciones de beneficio (portada)
- 6 descripciones de valor (`/nosotros`)

## Lo que sigue sin resolver

- **CNT-03** · Salabtún no tiene Instagram ni URL propia, así que su ficha sigue
  sin destino. Hace falta la dirección.
- **Discrepancia #1** · Las dos salas tienen datos idénticos en todos los campos.
  Confirmado en pantalla al ponerlas lado a lado. Sigue pendiente saber si la
  tercera tarjeta de 150 pax del original era una sala real.
- **Fotos** · Los beneficios usan fotos del espacio, no de cada beneficio; no hay
  foto real de la sala de creación de contenido; la de Salabtún es de 512 px.


---

# Cierre de los 50 hallazgos de la auditoria

`OK` cerrado · `~` parcial o pendiente de un dato externo · `NO` no procedía tal
como estaba redactado, con la razón.

## Fase 1 - Lo que estaba roto

| # | Estado | Qué se hizo |
|---|---|---|
| FE-16 | OK | `bg-tinta/88` no está en la escala de Tailwind y **no generaba ninguna regla CSS**, en silencio. Se comprobó que `/90` y `/95` sí producen reglas y `/88` ninguna. Corregido a `bg-tinta/[.88]`, y `tools/verificar-clases.php` (enganchado como `prebuild`) rompe la compilación si vuelve a aparecer. Prueba: `test_no_hay_clases_de_opacidad_invalidas`. |
| FE-01 | OK | `instagram.com/{cuenta}/embed` no está documentado por Meta y devuelve muro de inicio de sesión. Se pasa al endpoint soportado, uno por publicación (`/p/{code}/embed/`), con los permalinks en la tabla `ajustes` para poder cambiarlos sin desplegar. Prueba: `test_los_permalinks_de_instagram_son_de_publicacion`. |
| IMG-01 | OK | `public/hero-nodico.jpg`, 8.18 MB que no referenciaba nadie, eliminado. |
| FE-02 | OK | `ScrollReveal` vuelve a `IntersectionObserver` con tres redes de seguridad (`load`, `navigate` de Inertia, `orientationchange`) y un temporizador de 3 s como último recurso. |
| FE-03 | OK | El carrusel centra Nodo Pro al cargar. |
| PERF-01 | OK | Fachada del vídeo reescrita. El iframe se mantiene a `opacity-0` hasta 900 ms después del `load`, porque YouTube pinta negro mientras almacena y tapaba el póster. |

## Fase 2 - Peso y rendimiento

**5,294 KB -> 1,124 KB en escritorio y 772 KB en móvil.**

| # | Estado | Qué se hizo |
|---|---|---|
| IMG-02 | NO | El hallazgo daba por prescindible Carmen Sans Heavy. **`font-black` (900) se usa 53 veces** en dashboard, portal y autenticación, así que se conserva. El resto sí: subconjunto a latin español y WOFF2, **4,118.7 KB -> 81 KB** (`tools/fuentes.py`). |
| IMG-07 | NO | La premisa es falsa. Los 22 «SVG de Illustrator» no tienen un solo trazo: son un `<svg>` envolviendo un WebP en base64. SVGO y `currentColor` no aplican. Se extrajo el ráster y se reescaló a 160 px: **398 KB -> 43 KB** (`tools/iconos.php`). |
| FE-15 | NO | Parcialmente inválido: `.reveal-scale`, `.magnetic`, `.noise` y el bloque del cursor **los usan `Login.vue` y `Register.vue`**. Borrarlos habría roto la autenticación. Se eliminó solo el CSS realmente muerto. |
| IMG-03 | OK | `srcset` con variantes de 640 y 1280 px. |
| IMG-04 | OK | 1,150 KB de assets huérfanos eliminados. |
| IMG-05 | OK | Las fotos de plan salen del seeder. |
| IMG-06 | OK | Favicons y manifiesto regenerados (`tools/imagenes.php`). |
| FE-07 | OK | Póster del hero reencuadrado a horizontal. |
| FE-08 | OK | `width`/`height` corregidos; `verificar-clases.php` avisa si vuelven a divergir del archivo. |
| FE-09 | OK | `teaser-salones.png` (426x256) sustituido por `salon-yucatan-emprende-2.webp` (1920x1440). |
| PERF-02 | OK | El bucle de `lenis` se detiene con la pestaña oculta. |
| PERF-03 | OK | El mapa ya no está en las cinco páginas: vive solo en la sección de contacto y su iframe no se crea hasta que se pulsa. |
| PERF-04 | OK | `vue-sonner` sale del layout público; queda en los layouts autenticados. |
| IMG-09 | ~ | No se puede vectorizar sin el `.ai` original, que pesa 233 MB y no está en el repo. Se reexportó el PNG a 480x159. **Pendiente: el logo en vectorial.** |
| IMG-08 | ~ | `salabtun` mide 512x511 y se ve blanda. `dir-zentto` y las demás se reprocesaron. **Pendiente: foto original de Salabtún.** |

## Fase 3 - Metadatos, accesibilidad y datos

| # | Estado | Qué se hizo |
|---|---|---|
| SEO-01 | OK | `Meta.vue` emite Open Graph y Twitter Card; imágenes sociales 1200x630 generadas (`tools/og.php`). |
| SEO-02 | ~ | Canónica implementada y compartida desde el middleware, pero **`nodico.host_canonico` queda en `null`: falta saber si producción va con `www` o sin él.** |
| SEO-03 | OK | JSON-LD: `LocalBusiness` en el middleware, `OfferCatalog` en membresías y `Event` en comunidad. |
| SEO-04 | OK | El sufijo «— Nódico» se centraliza en `Meta.vue`. |
| SEO-05 | OK | `sitemap.xml` dinámico. Prueba: `test_el_sitemap_lista_todas_las_paginas`. |
| A11Y-01 / FE-10 | OK | El menú móvil es un `role="dialog"` real con `aria-modal`, `inert` sobre el fondo y foco atrapado, compartiendo `useBloqueoScroll` con el modal del hero. |
| A11Y-02 | OK | Flechas del carrusel corregidas. |
| A11Y-03 | OK | Los puntos usan el valor correcto y hay región `aria-live`. |
| A11Y-04 | OK | Contrastes recalculados componiendo los velos sobre los píxeles reales de cada foto (`tools/contraste.php`), que encontró **tres fallos de AA que la auditoría no vio**: portada de /nosotros 4.30, portada de /eventos 4.49 y teaser de salones 3.43 -> 7.01, 7.32 y 5.73. Los siete bloques de texto sobre imagen quedan entre 5.15 y 13.17. |
| A11Y-05 | OK | Los números «01...06» van `aria-hidden`. |
| A11Y-06 | OK | El honeypot ya no se posiciona contra el viewport. |
| FE-04 | OK | `@focusout` añadido al autoplay del carrusel. |
| FE-05 | OK | La detección de iPhone deja de depender de `navigator.platform`. |
| FE-06 | OK | Iframe y sección usan la misma unidad. |
| FE-11 | OK | `SiteHeader` y `HeroVideo` comparten `useBloqueoScroll`, con contador: ya no se pisan el `overflow` del documento. |
| FE-12 | OK | `resources/js/tipos.ts` con `Plan`, `Salon`, `Evento`, `Emprendedor` y `DatosNodico`. **No queda un solo `: any` en las cinco páginas públicas.** |
| FE-13 | OK | Corregido. |
| FE-14 | OK | El umbral antiarrastre sube de 6 a 12 px, por encima del temblor normal de un dedo. |
| CNT-01 | OK | Corregido. |
| CNT-02 | ~ | El directorio sale ya de la tabla `directorio_emprendedores` en lugar de estar codificado en el componente. **Se dejó sin CRUD de administración a propósito, por la restricción de no tocar `/dashboard`: hace falta tu visto bueno.** Pruebas: `test_el_directorio_llega_a_la_vista_desde_la_bd`, `test_un_emprendedor_inactivo_no_aparece`. |
| CNT-03 | ~ | El accesor `enlace` cae al Instagram del negocio, pero **Salabtún no tiene ni Instagram ni `url_destino`, así que su ficha sigue sin destino.** |
| BE-01 | OK | La prop `eventos` que nadie usaba ya no se envía. |
| BE-02 | OK | Límite doble: 30 envíos por IP y 3 por correo cada 10 minutos, para que un solo coworking detrás de una IP no se bloquee entero. Prueba: `test_el_contacto_limita_por_correo`. |
| BE-03 | OK | Se guarda `telefono` tal cual se escribió y `telefono_e164` normalizado. Prueba: `test_el_telefono_se_normaliza_a_e164`. |
| BE-04 | ~ | Los textos pasan a `resources/legal/*.md` y siguen marcados como provisionales. **Sigue en pie lo importante, que no es técnico: el aviso de privacidad y los términos están publicados sin que los haya validado el área jurídica del IYEM.** Prueba: `test_las_paginas_legales_vienen_de_markdown`. |
| BE-05 | OK | `Ajuste::obtener` ya no se traga cualquier `Throwable` en silencio. |

## Fase 6 - Lo que apareció al verificar

Hallazgos que no estaban en la lista de 50 y salieron al medir con Lighthouse móvil:

| Estado | Qué se encontró |
|---|---|
| OK | **21 usos de texto atenuado por debajo de 4.5:1.** Piso medido y documentado en el skill: `text-dark` nunca bajo `/70`, `text-white` nunca bajo `/60`. Accesibilidad 97 -> **100**. |
| OK | **`<article role="group">` no es válido**: `article` ya tiene rol implícito. La diapositiva del carrusel pasa a `<div>`, como pide la APG. |
| OK | **1,235 KB de terceros en cada visita.** El primer embed de Instagram iba con `loading="eager"` y los otros tres existían desde el primer render pese al `lazy`, en una sección muy por debajo del pliegue. Ahora el iframe no se crea hasta que la celda se acerca a pantalla. Peticiones 152 -> 97, terceros al cargar 1,235 KB -> **0 KB**, cookies de Meta fuera. Buenas prácticas 78 -> **100**. |
| OK | **El chunk de la página se descubría tarde.** Se importa dinámicamente desde `app.js`, así que el navegador no sabía que existía hasta ejecutar la entrada: `app.js` de 1326 a 1838 ms y solo entonces los chunks de página, de 1956 a 2305 ms. `App\Support\PrecargaVite` los declara por adelantado desde la vista raíz. |
| OK | **El póster del hero no se pedía hasta que Vue montaba la sección**, de modo que su `fetchpriority="high"` no servía de nada. Declarado en el `<head>`: pasa a bajar en el ms 1338, en paralelo con el JS. |

Speed Index 22.5 s -> 9.4 s · TBT 740 -> 410 ms · CLS 0.

**El Performance de Lighthouse no es medible en local.** Devuelve `NO_LCP`, aunque
Chrome real sí mide el LCP (el `<h1>`; el póster cubre el viewport entero y Chrome
lo descarta por considerarlo fondo). Y el TTFB de esta máquina oscila entre 1.1 s y
35 s con XAMPP y opcache frío, así que la cifra no significaría nada. Se toma contra
el servidor en la fase 7.

## Fase 7 - Despliegue

| # | Estado |
|---|---|
| OPS-01 | Pendiente: negarse a desplegar con el árbol de git sucio y sellar el hash del commit en el build. |
| OPS-02 | Pendiente: comparar los dos `manifest.json` y hacer una petición real con `curl` a un asset publicado. |

## Lo que necesita tu respuesta

1. **SEO-02** - ¿producción va con `www` o sin él?
2. **CNT-02** - ¿confirmas el directorio solo en base de datos, sin CRUD, para no tocar `/dashboard`?
3. **CNT-03** - ¿a dónde debe llevar la ficha de Salabtún?
4. **Discrepancia #1** - la tercera tarjeta «Yucatán Emprende 1» con 150 pax, ¿es una sala real o un duplicado? Hoy las dos salas tienen datos idénticos en todos los campos.
5. **BE-04** - el aviso de privacidad y los términos siguen sin validación jurídica del IYEM.
6. **IMG-08 / IMG-09** - falta la foto original de Salabtún y el logo en vectorial.
7. Las **17 líneas de texto** que redacté (6 servicios, 5 beneficios, 6 valores) y las descripciones de plan reescritas necesitan el visto bueno de Nódico. Las fotos de beneficios son del espacio, no de cada beneficio, y no hay foto real del estudio de creación de contenido.
