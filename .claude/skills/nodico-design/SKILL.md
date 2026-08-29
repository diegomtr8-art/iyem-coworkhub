---
name: nodico-design
description: Sistema de diseño «Editorial técnico» del sitio público y de las pantallas de acceso de Nódico (tokens, tipografía, patrones de componente, contraste de foco, movimiento y responsive). Usar al crear o modificar cualquier página o componente bajo resources/js/Pages/{Welcome,Nosotros,Membresias,Salones,Comunidad,Legal,Auth}, resources/js/Components/{Public,Auth} o resources/js/Layouts/AuthLayout.vue del proyecto coworkhub.
---

# Sistema de diseño de Nódico — «Editorial técnico»

Aplica al **sitio público** de `coworkhub` (`resources/js/Pages/{Welcome,Nosotros,Membresias,Salones,Comunidad,Legal}`,
`resources/js/Components/Public/`) y a las **pantallas de acceso** (`resources/js/Pages/Auth/`,
`resources/js/Layouts/AuthLayout.vue`, `resources/js/Components/Auth/`).
**No** aplica a `/dashboard` ni a `/portal`, que siguen con su propio lenguaje de panel.

## La idea

Revista de diseño con instrumental de ingeniería: titulares enormes, etiquetas monoespaciadas
numeradas como especificaciones técnicas, y tarjetas de borde duro con sombra sólida desplazada.
Público: emprendedores de 18 a 30 años en Yucatán. Juvenil y tecnológico, pero que un aliado
corporativo lo tome en serio. La seriedad viene del rigor de la retícula, no de apagar el color.

## Tokens — están en `tailwind.config.js`, no uses valores sueltos

### Color

| Token | Hex | Uso |
|---|---|---|
| `tinta` | `#1A1918` | Hero y footer: el oscuro de más peso |
| `dark` | `#2E2D2C` | Bandas oscuras, texto principal, bordes |
| `dark-600` | `#3D3C3A` | Superficies elevadas sobre oscuro |
| `cream` | `#F4F1EA` | Fondo claro base |
| `cream-50` | `#FAF8F3` | Fondo claro alterno |
| `cream-200` | `#E8E1D1` | Tarjetas sobre crema |
| `nodo-400` | `#FFE124` | Acento primario, CTAs |
| `lima` / `plan-pro` | `#D6E265` | Acento **solo sobre oscuro** |
| `morado` / `plan-match` | `#864B95` | Acento **solo sobre claro** |
| `coral` / `plan-daypass` | `#EF7E88` | Franja del plan Day-Pass |

### Reglas de contraste — medidas, no estimadas

- **Sobre amarillo `#FFE124`: siempre `dark` (10.5:1) o `tinta` (13.4:1).**
  Blanco sobre amarillo da **1.31:1** y está prohibido en este sistema.
- Sobre `dark`: `cream` 12.2:1 ✓, `nodo-400` 10.5:1 ✓, `lima` 9.8:1 ✓.
- **`morado` sobre `dark` da 2.26:1**: inutilizable como texto en oscuro. Solo relleno,
  o texto sobre crema (5.4:1).
- `coral` sobre `dark` 5.2:1 ✓.

#### Piso de opacidad para texto atenuado

Un texto de 12–14 px necesita 4.5:1. Componiendo `dark` sobre los cuatro fondos claros
del sistema, el peor caso es `cream-200`:

| Opacidad | white | cream | cream-50 | cream-200 |
|---|---|---|---|---|
| `/60` | 3.89 | 3.72 | 3.81 | 3.52 |
| `/65` | 4.50 | 4.28 | 4.39 | 4.02 |
| **`/70`** | **5.24** | **4.94** | **5.09** | **4.60** |

- **Texto oscuro atenuado: nunca por debajo de `text-dark/70`.** `/65` solo pasa sobre
  blanco puro, y basta cambiar la sección de fondo para romperlo.
- **Texto blanco atenuado: nunca por debajo de `text-white/60`.** `/50` falla sobre
  `dark-600` (4.09).
- Esto aplica también a `etiqueta-tecnica`, que mide 0.75 rem: es texto normal a efectos
  de WCAG, no texto grande.

### Tipografía

`font-display` = Carmen Sans (titulares) · `font-body` = GT Eesti Pro Display (texto) ·
`font-mono` = pila del sistema (etiquetas técnicas, sin descarga extra).

| Clase | Tamaño | Uso |
|---|---|---|
| `text-display-xl` | `clamp(2.75rem, 8vw, 6.5rem)` | Solo el `h1` del hero |
| `text-display-lg` | `clamp(2.5rem, 7vw, 5.5rem)` | Título de sección principal |
| `text-display-md` | `clamp(2rem, 5vw, 3.5rem)` | Título de sección secundario |
| `text-display-sm` | `clamp(1.5rem, 3vw, 2.25rem)` | Títulos de tarjeta |
| `text-cuerpo-lg` | `clamp(1.0625rem, 1.2vw, 1.25rem)` | Párrafos de entrada |
| `text-cuerpo` | `1rem` | Texto general |
| `text-etiqueta` | `0.75rem`, tracking `0.18em` | Etiquetas mono en mayúsculas |

No subas `display-xl` de `6.5rem`: por encima, el hero deja de caber en `100svh` y los CTA
se salen de pantalla. Ya pasó una vez.

### Sombras y easings

`shadow-dura-sm` (2px) · `shadow-dura` (6px) · `shadow-dura-lg` (10px) ·
`shadow-dura-nodo` / `shadow-dura-lima` / `shadow-dura-crema` para sombra en color.
Easings: `ease-salida` (entradas y hover) y `ease-suave` (salidas).

## Los cuatro patrones

**1 · Ficha técnica.** Toda sección abre con `<SectionHeading numero="01" etiqueta="SERVICIOS" …>`:
etiqueta mono numerada, línea de retícula a lo ancho, y debajo el titular grande.

**2 · Bloque duro.** La firma visual: `border-2 border-dark` + `shadow-dura`. Al hover la tarjeta
se desplaza y la sombra se cierra a 2px. **Solo se anima `transform`**, nunca `width`/`height`/`top`.

```html
<div class="border-2 border-dark bg-white shadow-dura-sm transition-all duration-200 ease-salida
            hover:-translate-x-1 hover:-translate-y-1 hover:shadow-dura">
```

**3 · Capas que se salen.** Imágenes que sangran fuera de la retícula, tarjetas montadas sobre la
costura entre dos bandas de color, la tarjeta del formulario flotando sobre el fondo. Profundidad
por superposición, nunca 3D falso ni degradados de relleno.

**4 · Cinta de marca.** `<Marquee :palabras="[...]" />` como separador entre secciones y en el footer.
La pista se duplica y la segunda copia va `aria-hidden`.

## Ritmo de página

Ninguna banda consecutiva repite fondo. Secuencia de referencia (portada):

`hero (tinta)` → `01 (cream)` → `cinta (nodo-400)` → `02 (dark)` → `03 (cream-50)` →
`04 (nodo-400)` → `05 (cream)` → `06 (dark)` → `hablemos (cream)` → `footer (tinta)`

Cada página debe tener su propia composición: no repitas la misma retícula en las cinco.

## Movimiento

- Solo `transform` y `opacity`. Micro-interacciones 200 ms, apariciones 600 ms, stagger 70 ms.
- Apariciones con `<ScrollReveal>`; para rejas usa `:stagger="70"` en el contenedor,
  que escalona los hijos directos.
- **`prefers-reduced-motion: reduce` apaga todo**: `app.css` lleva un interruptor global, y además
  cada componente con movimiento propio (carrusel, hero, marquee, volver-arriba) comprueba
  `window.matchMedia('(prefers-reduced-motion: reduce)').matches` y desactiva autoplay,
  video de fondo y scroll suave.

## Responsive — reglas duras

- Anchos de prueba obligatorios: **375, 393, 430, 744, 1024, 1366** px, más iPhone en horizontal.
- Área táctil mínima **44×44 px** en todo lo pulsable (`min-h-[44px]` en enlaces de lista y botones
  pequeños; los puntos del carrusel van dentro de un botón de 44px aunque el punto sea de 10px).
- **Cero scroll horizontal** en cualquier ancho. El carrusel desborda dentro de su contenedor,
  nunca en `documentElement`.
- Toda `<img>` lleva `width` y `height` explícitos (evita CLS) y `loading="lazy"` salvo el hero.
- Los `input` van a **16 px mínimo** (`app.css` lo fuerza): por debajo, iOS hace zoom al enfocar.
- Respeta el notch con la utilidad `.pb-segura` (`env(safe-area-inset-bottom)`).

## Componentes disponibles

`resources/js/Components/Public/`: `Boton`, `SectionHeading`, `IconCard`, `Marquee`,
`ScrollReveal`, `HeroVideo`, `PlanesCarousel`, `AliadosSection`, `ContactSection`,
`InstagramSection`, `SiteHeader`, `SiteFooter`, `StagingBanner`.

Antes de crear uno nuevo, revisa si `Boton` o `SectionHeading` ya cubren el caso. **No metas
dependencias de terceros**: el carrusel es `scroll-snap` nativo y el video una fachada de YouTube.

## Pantallas de acceso

Comparten `AuthLayout.vue`: composición partida, panel de marca a sangre a la izquierda
—foto del espacio con velo `tinta/55`, logo, frase grande y prueba social— y el formulario
a la derecha sobre `cream`, con ancho de lectura (`max-w-md`, o `max-w-lg` en el registro).
En iPhone el panel colapsa a una banda superior con el logo y el formulario ocupa el resto:
dos columnas a 375 px no son responsive.

- El titular del formulario va en `text-display-sm`; la frase del panel, en `text-display-md`.
  El panel grita, el formulario trabaja.
- Los campos son `CampoTexto.vue`, que reproduce el patrón del formulario «Hablemos»:
  etiqueta flotante, `bg-cream-50` que aclara a blanco al enfocar, borde `nodo-500` en foco.
- **El foco lleva dos colores, no uno.** El borde amarillo solo da 1.75:1 contra el blanco
  del campo enfocado y no cumple el criterio de foco visible, así que se acompaña de un
  contorno `outline-2 outline-offset-2 outline-dark`. Un anillo amarillo sobre crema es
  invisible; no lo uses como único indicador en ninguna parte del sistema.
- Botones sociales **arriba** del formulario, separados por una línea con «o con tu correo».
- Errores debajo de su campo y atados con `aria-describedby`, nunca en un bloque arriba.
- La prueba social del panel sale de la BD, con un piso: por debajo de 10 miembros no se
  muestra. Un dato real puede jugar en contra.

## Contenido

- Todo el texto de interfaz en **español con acentos correctos**.
- Los nombres de código (métodos, claves de props, componentes) van en **ASCII sin acentos**.
  Un `str_replace` de acentos sobre un controlador ya renombró `membresias()` a `membresías()`
  y tiró la ruta con un 500: nunca apliques correcciones de acentos sin acotarlas al texto visible.
- Precios, beneficios, salones y permalinks de Instagram salen de la **BD** (`NodicoWebSeeder`),
  nunca del componente.
