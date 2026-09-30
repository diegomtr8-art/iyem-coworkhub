# Módulo «Página Web» — Fase 0: inventario y alcance

> Rama `feature/cms-pagina-web`, a partir de `fb1a0c4` (29-sep-2026).
> Estado: **aprobado por Diego el 29-sep-2026** con las decisiones de la sección 4.
> El bug 1.1 y la sala de juntas ya están corregidos en `4ae69ec`.

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
| Dirección larga y corta | `.env` | **E** | El JSON-LD usa la dirección postal estructurada de `negocio` (2.9). |
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

### 2.9 SEO y datos estructurados (dentro, por decisión 5)

| Elemento | Hoy | Col. | Nota |
|---|---|---|---|
| Título y descripción de cada página (7 rutas) | `nodico.seo_paginas` | **E** | Tope: título 60 y descripción 160 caracteres, que es lo que Google enseña. La pantalla muestra la vista previa del resultado de búsqueda y de la tarjeta de WhatsApp. |
| Imagen social de cada página | `/img/og/*.jpg` | **E** | 1200×630, **JPG** (WhatsApp y LinkedIn no leen WebP). Es el único hueco que no se convierte a WebP. |
| Sufijo del título («Nódico») | config | **F** | Es la marca. |
| JSON-LD: descripción del negocio | escrito a mano en `fichaNegocio()` | **E** | |
| JSON-LD: dirección postal (calle, localidad, región, CP) | escrito a mano | **E** | Una sola fuente también para el `address` de los eventos en Comunidad (1.2). |
| JSON-LD: coordenadas | escrito a mano | **E** | Se validan como número en rango y dentro de Yucatán, para que un dígito de más no mande a Nódico al mar. |
| JSON-LD: horario de apertura | escrito a mano | **C** | Sale de `nodico.operacion` (días, apertura, cierre), que es la regla del motor de reservas. Una sola fuente. |
| JSON-LD: redes (`sameAs`), correo, teléfono | config | **E** | Salen de `contacto` y `redes`; no se piden dos veces. |
| Host canónico, `robots` | config/entorno | **C** | |

### 2.10 Fuera del alcance

- **Textos legales** (`resources/legal/*.md`): los revisa jurídico y quedan en
  git con historial. Quedan fuera.
- **Planes, salones, espacios, eventos, emprendedores, anuncios**: ya tienen su
  módulo. Cada pantalla del CMS enlaza al módulo que corresponde.
- **Colores, tipografías, orden de secciones, maquetación, iconos de `lucide`**:
  fijos.

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

seo.<ruta>          { titulo, descripcion, imagen }      # home, nosotros, membresias, eventos, actividades, privacidad, terminos
negocio             { descripcion, calle, localidad, region, codigo_postal, latitud, longitud }
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

## 4. Decisiones tomadas (Diego, 29-sep-2026)

1. **Secciones que se pueden apagar:** Espacios, Beneficios, Day-pass, teaser de
   Salones, Aliados, Instagram, teaser de Comunidad, Valores y Coffee break.
   No se pueden apagar: hero, Servicios, Membresías, «Hablemos» ni las portadas.
2. **Iconos de Servicios y Valores:** fijos.
3. **Aliados:** se editan nombre y enlace de los tres actuales. No se añaden ni
   se quitan aliados, y los logos no se cambian.
4. **Fotos compartidas:** cada hueco es independiente y parte de la foto de hoy
   como respaldo.
5. **SEO y JSON-LD: dentro** (2.9), con vista previa de la tarjeta.
6. **Frases de las pantallas de acceso:** fijas. Solo la foto de fondo es
   editable.
7. **Cantidad de elementos:** Servicios 6 fijos (2 + 4); Espacios de 2 a 6, en
   pares; Beneficios de 3 a 6; Valores de 3 a 9; Incluido de 2 a 8; Pasos 3
   fijos.
8. **Bug 1.1:** corregido en esta rama (`4ae69ec`), con la prueba
   `EncabezadosDeSeccionTest` para que no se repita.
9. **Sala de juntas:** «1 disponible» (`4ae69ec`).

## 4 bis. Cómo quedó la migración (Fase 4, 29-sep-2026)

Cinco pasos (`07f780a` a `2430e61`). Después de cada uno se comparó la
«huella» de las siete páginas públicas contra la de antes de empezar: texto
visible, cada `<img>` (src, srcset, alt, medidas) y cada enlace, y el
`<head>` del servidor (título, descripción, `og:`) y el JSON-LD. Con `ajustes`
vacía, todas quedaron **idénticas**. La única diferencia fue buscada: el
`tel:` de la portada ahora lleva `+52`, como el resto.

Dónde vive cada cosa: `app/Servicios/Sitio/Paginas/*` (una clase por página;
`Fotos.php` guarda las fotos fijas). El diseño que va por posición (iconos,
colores de los paneles, tamaño de celda) sigue en los componentes.

Diferencias con lo acordado en la sección 4, y su motivo:

| Punto | Acordado | Cómo quedó | Por qué |
|---|---|---|---|
| Valores | 3 a 9 | **3 o 6** | Hay seis iconos y van por posición (decisión 2): un séptimo valor saldría sin icono. |
| Descripción SEO | 160 caracteres | **220** | Las de hoy ya pasan de 160. Con 160, el propio respaldo no cumpliría su regla y la sección no se podría guardar. |
| «120 personas» en Comunidad | Salir de la BD | **Texto editable**, con aviso en la ayuda | Es una frase, no un dato suelto; partirla en trozos para meter la cifra complica el campo más de lo que aporta. |
| Beneficios | 3 a 6 | 3 a 6; con 6, **el sexto repite el color del primero** | La paleta tiene cinco acentos. |
| Aliados: apagar | La sección | Solo la **franja de la portada** | Los logos del pie son parte del pie. |

Tipos de campo: texto, párrafo, correo, teléfono, URL (https y host
permitido), usuario de Instagram, id de YouTube, número en rango, interruptor,
foto (formato, alt obligatorio salvo decorativas) y lista (mínimo, máximo y
múltiplo). Una prueba exige que **todo respaldo cumpla sus propias reglas**.

## 4 ter. Fases 5 y 6: comprobado en el servidor de pruebas (30-sep-2026)

Con `f61ec48` publicado y un administrador temporal (autorizado por Diego,
borrado al terminar), se ejerció el panel por HTTP contra
prueba.nodico.com.mx: sesión, CSRF, subida y guardado, el mismo camino que el
navegador.

| Prueba (Fase 6) | Resultado |
|---|---|
| Con `ajustes` vacía el sitio es idéntico | Huella de las siete páginas idéntica tras cada paso (local) y pruebas automáticas |
| Guardar cambia el sitio y deshacer lo devuelve | Day-pass con foto subida → portada la sirve; deshacer → vuelve la fija |
| Un valor corrupto no rompe la página | Pruebas automáticas (fila basura, tabla borrada, foto que desaparece) |
| Staff y Caja no alcanzan el módulo | 403 por pantalla y por ruta, incluida la subida de fotos |
| Una imagen subida genera sus tamaños y se sirve | Subida por el panel → `800.webp` y `1600.webp`, 200, `image/webp`. **PHP-FPM 8.3 genera WebP.** |
| Una imagen subida sobrevive a un despliegue | Redespliegue completo: mismos archivos, mismo peso, la portada la sigue usando |
| `<script>` se pinta como texto | Prueba automática: sale en las props y nunca sin escapar en el HTML |
| La caché se invalida al guardar | El cambio se vio en la petición siguiente; prueba automática |
| Cada cambio queda en la bitácora | «Inicio · «Day-pass gratuito»: foto», con autor, antes y después |

Restos de la prueba en el servidor: dos entradas de bitácora (autor vacío, al
borrarse la cuenta temporal) y la imagen #2 de `imagenes_sitio`, sin usar.

## 5. Lo que quedará fuera (resumen)

- Constructor de páginas, bloques móviles, HTML libre, tipografías y colores.
- Precios y capacidades (viven en Planes, Espacios y `config`).
- Embeds de terceros como URL libre (mapa, Luma): solo por `.env`.
- Textos legales.
- Menús, etiquetas de formularios e instrucciones de la interfaz.
- Añadir aliados o iconos nuevos.
