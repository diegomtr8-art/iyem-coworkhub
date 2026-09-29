# Módulo «Página Web» — Fase 0: inventario y alcance

> Rama `feature/cms-pagina-web`, a partir de `fb1a0c4` (29-sep-2026).
> Estado: **propuesta, pendiente de visto bueno.** Todavía no hay código.

Este documento fija **qué** se va a poder editar desde el panel antes de construir
**cómo**. Un módulo de contenido que cubre medio sitio es peor que ninguno: nadie
sabe qué parte se cambia desde el panel y qué parte exige a un programador.

---

## 1. Hallazgos que hay que resolver antes de la Fase 1

Salieron al hacer el inventario. Varios cambian lo que significa «el valor que
hoy está en el código», que es justo el respaldo sobre el que se apoya todo el
módulo.

### 1.1 Faltan títulos de sección en las cinco páginas públicas (bug en vivo)

El commit `8b983b7` (`fix(seo)`, 29-ago-2026) sacó `titulo=` y `descripcion=` de
los `<Meta>` para llevarlos a `config/nodico.php`. Pero el cambio también se
llevó por delante los mismos atributos de **los `SectionHeading`**. Desde ese
día, estas secciones pintan la etiqueta pequeña («Servicios», «Visión»…) y
debajo un `<h2>` **vacío**. `titulo` es obligatorio en el componente, y
TypeScript no lo detectó porque las páginas no pasan por `vue-tsc` en el build.

| Página | Sección | Título perdido | Descripción perdida |
|---|---|---|---|
| Inicio | Servicios | Todo incluido en tu membresía | — |
| Inicio | Beneficios | Y otras cosas que solo pasan aquí | — |
| Inicio | Membresías | Elige tu plan ideal | El éxito comienza con el entorno correcto. Cada membresía te da la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto. |
| Inicio | Salones | Espacios listos para tu evento | Talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida. |
| Nosotros | Visión | El referente del sureste de México | — |
| Membresías | Sin planes (vacío) | Membresías en actualización | Estamos afinando los planes. Escríbenos y con gusto te compartimos los precios vigentes. |
| Membresías | Siempre incluido | Da igual el plan que elijas | Hay cosas que no dependen de la membresía: vienen con el simple hecho de ser parte de Nódico. |
| Membresías | Cómo funciona | De la compra al escritorio | — |
| Comunidad | Talleres | Conoce los talleres del mes | — |
| Comunidad | Directorio | Conoce a la comunidad | Emprendedores y empresas que forman parte o han egresado de nuestros programas de incubación del IYEM. |
| Salones | Sin salones (vacío) | Salones en actualización | Estamos preparando la información de nuestros salones. Escríbenos y te compartimos disponibilidad y precios. |
| Salones | Coffee break | Coffee break para tu evento | — |

**Propuesta:** restaurarlos con un `fix` aparte **antes** de la Fase 1, con los
textos originales de la tabla. Si no, el respaldo del módulo congelaría
encabezados vacíos, y la prueba «con `ajustes` vacía el sitio se ve igual que
hoy» daría por bueno un sitio roto.

### 1.2 Datos que se contradicen entre sí

| Dato | Dónde dice una cosa | Dónde dice otra |
|---|---|---|
| Salas de juntas | `Welcome.vue` → «2 disponibles» | commit `d63cc05`: en los datos reales hay **una sola** |
| Precio del coffee break | `Salones.vue` → $45 / $35, escritos a mano | `config('nodico.salones.coffee')`, que es lo que usa el cotizador |
| Capacidad de los salones | Comunidad → «120 personas», escrito a mano | `Espacio::salonesPublicados()` en BD |
| Horario | `nodico.horarios` (texto) | `nodico.operacion.apertura/cierre` y el JSON-LD de `HandleInertiaRequests`, que también lo tiene escrito a mano |
| Dirección y coordenadas | `nodico.direccion` | el JSON-LD (`fichaNegocio()`) y `Comunidad.vue` (`address` de cada `Event`), ambos escritos a mano |
| Cómo funciona, paso 1 | «El cobro se procesa por Stripe» | con `PAGOS_PASARELA=bbva` es falso |

**Propuesta:** en la migración, cada uno de estos datos se lee de **una sola**
fuente. Los precios y capacidades se leen de donde ya viven (config o BD) y **no
se meten en el CMS**. Si no, habría dos sitios que editar y acabarían diciendo
cosas distintas.

### 1.3 Configuración muerta

- `config('nodico.video_id')` (`NODICO_VIDEO_ID`) **nadie lo lee**. El video del
  hero sale del valor por defecto escrito dentro de `HeroVideo.vue`. Cambiar la
  variable en el `.env` hoy no hace nada.
- El ajuste `instagram_posts`, que siembra `NodicoWebSeeder`, **nadie lo lee**
  desde que el muro se cambió por el embed del perfil (`7aee3b8`). Es la única
  fila que hoy tiene la tabla `ajustes`.

### 1.4 Imágenes: lo que hay de verdad en el servidor

Comprobado por SSH (solo lectura) y con `curl` el 29-sep-2026:

- **El enlace simbólico sí existe**: `public_html/public/storage → ../storage/app/public`
  (creado a mano el 10-jul, porque `exec()` está deshabilitado y `storage:link`
  falla). **Pero no sirve para nada**: el document root es `public_html/`, no
  `public_html/public/`. La URL `/storage/…` cae en `public_html/storage/`, que
  es el `storage/` **de la aplicación**, blindado con `.htaccess` (→ **403**).
- Consecuencia que ya existe hoy: los avatares que sube la app móvil
  (`YoController`, disco `public`) no se pueden servir por HTTP en pruebas.
- `deploy_prueba.py` no tiene `DIRS_ESTATICOS`; lo que hay es `DIRS_ASSETS`
  (`build`, `img`, `fonts`, `icons`). La sincronización **solo añade y
  sobrescribe, nunca borra**. «Sueltos de `public/`» copia solo archivos, no
  carpetas.

**Propuesta para la Fase 2:** las imágenes subidas desde el panel van a
`public_html/medios/sitio/`, a través de un disco propio (`medios`) con raíz en
`public_path('medios')`. No usan el disco `public` ni el enlace simbólico. Es una
carpeta que ni `DIRS_ASSETS` ni los sueltos van a tocar nunca. En local,
`/public/medios/` va al `.gitignore`. Antes de construir hay que comprobar dos
cosas: que en el servidor `public_path()` apunta a `public_html/` y que se sirve
con `curl`. Todo esto queda escrito en `docs/DEPLOY.md`.

---

## 2. Inventario

**E** = editable desde el panel · **C** = configuración (`.env`/`config`) ·
**F** = fijo (diseño y estructura) · **M** = ya tiene su módulo (se enlaza, no
se duplica) · **?** = a medio camino, **necesita tu decisión** (sección 4).

### 2.1 Global: datos de contacto y redes (`config/nodico.php` → prop `nodico`)

| Elemento | Hoy | Col. | Nota |
|---|---|---|---|
| Correo de contacto | `.env` `NODICO_CONTACTO_EMAIL` | **E** | También es el destinatario del formulario «Hablemos». Hay que validar que sea un correo. |
| Teléfono visible y teléfono E.164 | `.env` | **E** | Un solo campo: el E.164 se deriva, no se pide aparte. |
| Dirección larga y corta | `.env` | **E** | El JSON-LD sigue usando la dirección postal estructurada (ver ?5). |
| URL de «Cómo llegar» | `.env` | **E** | Solo `https://`. |
| Embed del mapa | `.env` | **C** | Es una URL de iframe y afecta a la CSP. Un campo libre aquí es una forma de meter cualquier iframe en todas las páginas. |
| Horario (texto) y detalle | `.env` / config | **E** | Solo el texto que se ve. El horario que valida reservas (`operacion.*`) sigue siendo **C**. |
| Instagram, Facebook, LinkedIn | config | **E** | Validar dominio de cada red. Vacío = el icono no sale. |
| Usuario de Instagram (embed) | config | **E** | Se valida contra `^[A-Za-z0-9._]{1,30}$`. Alimenta el iframe de Instagram. |
| Video de YouTube (id) | incrustado en `HeroVideo.vue` | **E** | Solo el id (`^[A-Za-z0-9_-]{11}$`), nunca una URL ni un embed. |
| Calendario Luma (embed) | `.env` | **C** | Igual que el mapa: iframe y CSP. |
| Datos bancarios, pagos, reservas, app móvil, SEO técnico, CSP, acceso | config | **C** | Fuera del módulo. |

### 2.2 Inicio (`Welcome.vue` + `HeroVideo.vue`)

| Sección | Elemento | Col. | Nota |
|---|---|---|---|
| Hero | «Bienvenidos al lugar», titular, subtítulo | **E** | Tope: titular 60 caracteres. |
| Hero | Póster `hero-inicio` (9:16, 3 tamaños) y su alt | **E** | Imagen. |
| Hero | Textos de los botones | **F** | Son navegación. |
| Servicios | Título y descripción de sección | **E** | Hoy perdidos (1.1). |
| Servicios | `servicios`: título y descripción de cada uno | **E** | |
| Servicios | Foto de las 2 protagonistas | **E** | Imagen 16:9. |
| Servicios | Iconos | **?** | ?2 |
| Servicios | Cuántos hay y cuáles son protagonistas | **F** | El mosaico es 2 grandes + 4 compactos. |
| Espacios | Título y descripción de sección | **E** | |
| Espacios | `espacios`: nombre, cantidad, capacidad, descripción, foto (4:3) y alt | **E** | Cantidad y capacidad son texto libre corto, no número. |
| Beneficios | Título de sección | **E** | Hoy perdido (1.1). |
| Beneficios | `beneficios`: título, título corto, descripción, foto | **E** | Tope en título corto: va en vertical. |
| Beneficios | `acento` (color de cada panel) | **F** | Paleta del sistema de diseño. |
| Membresías | Título y descripción de sección | **E** | Hoy perdidos (1.1). |
| Membresías | Planes | **M** | Módulo Planes. `planesFallback` se queda como respaldo técnico. |
| Day-pass | Etiqueta, titular, sello, párrafo, texto del sello giratorio | **E** | |
| Day-pass | Foto `daypass-emprendedor` (4:3) y alt | **E** | |
| Salones | Título y descripción de sección | **E** | Hoy perdidos (1.1). |
| Salones | Ficha (medidas, capacidad, precio) | **M** | Módulo Espacios. |
| Salones | Foto de fondo `salon-yucatan-emprende-2` y alt | **E** | |
| Aliados | «Con el respaldo de» | **E** | |
| Aliados | Logos, nombres, enlaces | **?** | ?3 |
| Instagram | «Lo que pasa en Nódico» | **E** | Compartido con Comunidad. |

### 2.3 Nosotros (`Nosotros.vue`)

| Sección | Elemento | Col. | Nota |
|---|---|---|---|
| Portada | Etiqueta, «¿Quiénes somos?», párrafo | **E** | |
| Portada | Foto `nosotros-hero` y alt | **E** | |
| Misión | Título y dos párrafos | **E** | Párrafos como lista de textos, sin HTML. |
| Misión | Foto `mision` y alt | **E** | Se reutiliza en Servicios y Beneficios del inicio: son **huecos distintos** que hoy comparten archivo. |
| Visión | Título (perdido) y texto | **E** | |
| Visión | Foto de fondo `vision` | **E** | Decorativa: no lleva alt. La capa oscura del 88 % es **F**. |
| Valores | Título de sección | **E** | |
| Valores | `valores`: título y descripción | **E** | Iconos: ?2. |

### 2.4 Membresías (`Membresias.vue`)

| Sección | Elemento | Col. | Nota |
|---|---|---|---|
| Portada | Etiqueta, titular, párrafo | **E** | El párrafo dice «Cuatro planes»: lo ideal es que no dé la cifra, porque los planes viven en otro módulo. |
| Planes | Planes y estado vacío | **M** / **E** | Los planes, módulo Planes. Los textos del estado vacío, **E** (perdidos, 1.1). |
| Siempre incluido | Título, descripción, `incluidoEnTodas` | **E** | |
| Cómo funciona | Título, `comoFunciona` (título y texto) | **E** | Los iconos, **F**: son de `lucide`, no imágenes. El paso 1 menciona Stripe (1.2). |
| Cómo funciona | Nota del day-pass gratuito | **E** | |

### 2.5 Comunidad (`Comunidad.vue`, ruta `/actividades`)

| Sección | Elemento | Col. | Nota |
|---|---|---|---|
| Portada | Etiqueta, titular, párrafo | **E** | |
| Portada | Foto `comunidad-fondo` (2:1) | **E** | La misma foto va de fondo en login y registro (2.8). ?4 |
| Talleres | Título (perdido) y párrafo | **E** | El embed de Luma es **C**. |
| Emprendedor de la semana y Directorio | Datos | **M** | Módulo Emprendedores. El título y la descripción del Directorio, **E** (perdidos, 1.1). |
| Teaser de salones | Titular y párrafo | **E** | «120 personas» debe salir de la BD (1.2). |

### 2.6 Salones (`Salones.vue`, ruta `/eventos`)

| Sección | Elemento | Col. | Nota |
|---|---|---|---|
| Portada | Etiqueta, titular, párrafo, foto `salon-yucatan-emprende-1` (9:16) y alt | **E** | |
| Salas | Descripción, ficha, «incluye», foto de cada sala | **M** | Módulo Espacios. |
| Salas | «Ambas salas incluyen», «Sala 0N» | **F** | |
| Coffee break | Título (perdido) y foto de fondo | **E** | |
| Coffee break | Precios y tramos | **C** | Tienen que salir de `nodico.salones.coffee` (1.2). |

### 2.7 Pie, cabecera y bloque «Hablemos» (todas las páginas)

| Elemento | Col. | Nota |
|---|---|---|
| Llamado final («¿Listo para empezar?» y párrafo) | **E** | |
| Texto de marca del pie | **E** | |
| Texto de facturación | **E** | Sin HTML: el correo se inserta solo. |
| Leyenda legal («marca registrada…») | **E** | |
| Menús (etiquetas y rutas), logo de Nódico | **F** | |
| «Hablemos»: etiqueta, titular y párrafo | **E** | |
| Etiquetas y validaciones del formulario | **F** | |

### 2.8 Pantallas de acceso (`AuthLayout.vue` y `Pages/Auth/*`)

| Elemento | Col. | Nota |
|---|---|---|
| Foto de fondo (hoy `comunidad-fondo`) | **E** | ?4 |
| Frase del panel lateral (una por pantalla, 10 pantallas) | **?** | ?6 |
| Títulos, subtítulos y etiquetas de los formularios | **F** | Son instrucciones de la interfaz, no contenido. |
| Cifras de «prueba social» | **F** | Se calculan (`CompartirMarcaDeAcceso`). |

### 2.9 Fuera del alcance

- **SEO por página** (`nodico.seo_paginas`) y **JSON-LD**: ?5.
- **Textos legales** (`resources/legal/*.md`): los revisa jurídico y quedan en
  git con historial. Quedan fuera.
- **Planes, salones, espacios, eventos, emprendedores, anuncios**: ya tienen su
  módulo. Cada pantalla del CMS enlaza al módulo que corresponde.
- **Colores, tipografías, orden de secciones, maquetación, iconos de `lucide`**:
  fijos.
- **Imágenes OG** (`/img/og/*.jpg`, 1200×630): van con el SEO (?5).

---

## 3. Esquema de claves propuesto

Una fila de `ajustes` **por sección**, no por campo. El `valor` JSON lleva la
sección completa. Así, guardar por sección (Fase 3) es una sola escritura, la
versión anterior se guarda de una vez y deshacer devuelve la sección entera, no
un campo suelto.

```
contacto            { email, telefono, direccion, direccion_corta, maps_url, horarios, horarios_detalle }
redes               { instagram, facebook, linkedin, instagram_usuario }
video               { youtube_id }

inicio.hero         { antetitulo, titulo, subtitulo, imagen }
inicio.servicios    { titulo, descripcion, visible, elementos: [{ titulo, descripcion, imagen? }] }
inicio.espacios     { titulo, descripcion, visible, elementos: [{ nombre, cantidad, capacidad, descripcion, imagen }] }
inicio.beneficios   { titulo, visible, elementos: [{ titulo, titulo_corto, descripcion, imagen }] }
inicio.membresias   { titulo, descripcion }
inicio.daypass      { etiqueta, titulo, sello, texto, sello_giratorio, imagen, visible }
inicio.salones      { titulo, descripcion, imagen, visible }
inicio.aliados      { etiqueta, visible }

nosotros.portada    { etiqueta, titulo, texto, imagen }
nosotros.mision     { titulo, parrafos: [], imagen }
nosotros.vision     { titulo, texto, imagen }
nosotros.valores    { titulo, visible, elementos: [{ titulo, descripcion }] }

membresias.portada  { etiqueta, titulo, texto }
membresias.vacio    { titulo, descripcion }
membresias.incluido { titulo, descripcion, elementos: [] }
membresias.pasos    { titulo, elementos: [{ titulo, texto }], nota }

comunidad.portada   { etiqueta, titulo, texto, imagen }
comunidad.talleres  { titulo, texto }
comunidad.directorio{ titulo, descripcion }
comunidad.teaser    { titulo, texto, visible }

salones.portada     { etiqueta, titulo, texto, imagen }
salones.vacio       { titulo, descripcion }
salones.coffee      { titulo, imagen, visible }

comun.instagram     { titulo, visible_inicio, visible_comunidad }
comun.hablemos      { etiqueta, titulo, texto }
comun.pie           { llamado_titulo, llamado_texto, marca, facturacion, leyenda }
acceso              { imagen_fondo }
```

`imagen` nunca es una ruta libre. Es una referencia `{ id, alt }` a una imagen
del disco `medios` (o al archivo de `/img/nodico/` que se usa como respaldo). El
servidor arma el `srcset`. Así, el administrador no puede apuntar el sitio a una
URL externa.

`visible` solo existe en las secciones que se pueden apagar sin dejar la página
coja (ver ?1).

El respaldo de cada clave vive **una sola vez**, en
`App\Servicios\Sitio\ContenidoDelSitio`, y el literal desaparece del componente
cuando la sección se migra. Los datos de contacto usan como respaldo
`config('nodico.*')`, así que el `.env` sigue funcionando como valor por defecto
de cada entorno.

---

## 4. Decisiones que necesito de ti antes de la Fase 1

1. **Encender y apagar secciones.** Propongo que se puedan apagar: Espacios,
   Beneficios, Day-pass, teaser de Salones, Aliados, Instagram, teaser de
   Comunidad, Valores y Coffee break. Que **no** se puedan apagar: hero,
   Servicios, Membresías, «Hablemos» y las portadas de cada página, porque sin
   ellas la página no se sostiene. ¿De acuerdo?
2. **Iconos de Servicios y Valores** (PNG/WebP propios, monocromos). Cambiarlos
   exige un icono en el mismo estilo, y el panel no puede garantizarlo.
   Propuesta: **fijos**. Si se añade un servicio nuevo, lo hace el diseño.
3. **Aliados.** Propuesta: editar **nombre y enlace** de los tres actuales, pero
   **no** añadir ni quitar aliados ni cambiar logos. Cada logo se ajusta a mano
   (altura óptica, fondo transparente, recorte a tinta), y un logo subido tal
   cual rompe la franja.
4. **Una foto compartida en varios sitios** (`mision`, `comunidad-fondo`,
   `salon-detalle`, `nosotros-hero`). Propuesta: en el CMS **cada hueco es
   independiente** y parte con la misma foto de hoy como respaldo. Cambiar la
   foto de Misión no debería cambiar sin avisar un panel de Beneficios del
   inicio.
5. **SEO por página y JSON-LD.** Propuesta: fuera de esta primera entrega. Un
   título SEO mal puesto no se ve en la página, pero se hereda en Google y en
   cada enlace compartido por WhatsApp. Si lo quieres dentro, va en una fase
   aparte con vista previa de la tarjeta.
6. **Frases de las pantallas de acceso.** Propuesta: fijas. Son diez, cada una
   pensada para su pantalla, y nadie de coordinación va a buscarlas ahí. Solo la
   foto de fondo es **E**.
7. **Cantidad de elementos en las listas.** Propuesta: Servicios, 6 fijos
   (2 + 4). Espacios, de 2 a 6 (grid de dos columnas, siempre en pares).
   Beneficios, de 3 a 6. Valores, de 3 a 9 (múltiplos de 3 en escritorio).
   Incluido, de 2 a 8. Pasos, 3 fijos. Fuera de esos rangos se rompe la
   maquetación.
8. **El bug 1.1**: ¿lo arreglo ya, en un `fix` aparte con los textos originales?
   ¿Y en qué rama: `feature/pagos-bbva`, que es la que está en pruebas, o
   directamente en esta?
9. **Salas de juntas**: ¿«1 disponible»?

---

## 5. Lo que quedará fuera (resumen)

- Constructor de páginas, bloques móviles, HTML libre, tipografías y colores.
- Precios y capacidades (viven en Planes, Espacios y `config`).
- Embeds de terceros como URL libre (mapa, Luma): solo por `.env`.
- Textos legales, SEO y JSON-LD (salvo que decidas lo contrario en ?5).
- Menús, etiquetas de formularios e instrucciones de la interfaz.
- Añadir aliados o iconos nuevos.
