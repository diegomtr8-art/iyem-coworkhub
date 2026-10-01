# Prompt para Claude Code — Módulo «Página Web» en el panel de administración

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## LO QUE SE QUIERE

Hoy, cambiar un teléfono, una foto, un enlace o un párrafo del sitio público **exige un programador**: los textos viven dentro de los componentes de Vue, las imágenes dentro del repositorio y los datos de contacto dentro de `config/nodico.php`, que lee del `.env` del servidor.

Se quiere un módulo **«Página Web»** en el panel de administración desde el que la coordinación de Nódico pueda cambiar el contenido del sitio público **sin tocar código y sin esperar a nadie**.

**Rama:** `feature/cms-pagina-web`.

---

## DÓNDE ESTÁ LA LÍNEA, Y POR QUÉ IMPORTA

Esto **no** es un constructor de páginas. Si el administrador puede mover bloques, elegir tipografías y meter HTML libre, dos cosas pasan siempre: el sitio se sale del sistema de diseño de Nódico y se vuelve imposible de mantener. Y la primera vez que alguien pegue HTML de un correo, la portada se rompe.

Lo que sí se puede cambiar: **textos, imágenes, enlaces, datos de contacto, y encender o apagar secciones enteras.**

Lo que **no**: la maquetación, los colores, las tipografías, el orden de las secciones ni la estructura de las páginas. Eso lo fija el diseño, no el contenido.

Si algo se te queda a medio camino entre las dos listas, **pregunta antes de construirlo**.

---

## LO QUE YA EXISTE Y HAY QUE APROVECHAR

Antes de escribir nada, léelo:

- **La tabla `ajustes` ya existe y nadie la usa.** `App\Models\Ajuste` tiene `clave` única, `valor` en JSON, `obtener()` y `guardar()`, y `obtener()` ya está blindado para no reventar si la tabla no existe. **Ésa es la base de este módulo.** No crees otra tabla de clave/valor.
- **Los permisos ya están modelados** en `app/Providers/AuthServiceProvider.php`, en tres grupos (`OPERACION`, `ADMINISTRACION`, `CAJA`) con `permisosDeRol()`. Aquí se añade uno nuevo.
- **La bitácora ya existe** (`EntradaBitacora`). Todo cambio de contenido se registra ahí, con quién y cuándo.
- **El menú del panel** está en `resources/js/Layouts/AuthenticatedLayout.vue`.
- **Las propiedades compartidas** salen de `app/Http/Middleware/HandleInertiaRequests.php`, donde hoy se arma `nodico` desde `config('nodico.*')`.
- **Hay módulos de administración ya hechos** —Anuncios, Emprendedores, Eventos, Espacios— con su patrón de controlador, validación y pantalla. Síguelo; no inventes uno nuevo.

---

# FASE 0 — Inventario, y un entregable antes de tocar código

**No escribas código en esta fase.** El alcance de esto es ilimitado si no se acota primero, y un módulo de contenido a medio hacer es peor que ninguno: deja medio sitio editable y medio no, sin que nadie sepa cuál es cuál.

Recorre las **cinco páginas públicas** (`Welcome.vue`, `Nosotros.vue`, `Membresias.vue`, `Comunidad.vue`, `Salones.vue`) más `PublicLayout.vue`, el pie de página y las pantallas de acceso, y haz el inventario completo de **todo** lo que hoy está incrustado. Ya sé de varios sitios; búscalos todos, no solo éstos:

- Los arreglos literales de `Welcome.vue`: `servicios`, `espacios`, `beneficios`, `planesFallback`.
- `valores` en `Nosotros.vue`; `incluidoEnTodas` y `comoFunciona` en `Membresias.vue`.
- Los textos de misión y visión, los encabezados de sección y sus descripciones.
- Todas las rutas de imagen bajo `/img/nodico/`.
- Los enlaces salientes: IYEM, Herencia Viva, Canieti, redes sociales.
- El identificador del video de YouTube y el embed de Instagram.
- Los datos de contacto y el mapa, que hoy vienen de `config/nodico.php` **vía `.env` del servidor** — cambiarlos hoy exige entrar por SSH.

Clasifica **cada elemento** en una de tres columnas, y justifica las dudosas:

| Columna | Qué va aquí |
|---|---|
| **Editable** | Contenido que la coordinación va a querer cambiar sola. |
| **Configuración** | Cosas técnicas que se quedan en `.env` o en `config/` (claves, dominios, tiempos de sesión). Meterlas en un formulario es regalar formas de romper el sitio. |
| **Fijo** | Estructura y diseño. No se toca desde ningún panel. |

**Entregable:** `docs/CMS-PAGINA-WEB.md` con ese inventario, el esquema de claves propuesto y la lista de lo que quedará fuera. **Enséñamelo y espera visto bueno antes de la Fase 1.**

---

# FASE 1 — El cimiento: contenido con respaldo

Un servicio único, `App\Servicios\Sitio\ContenidoDelSitio`, que resuelve cada clave así:

1. ¿Hay una fila en `ajustes`? Úsala.
2. ¿No hay? **Devuelve el valor que hoy está en el código.**

Ese respaldo no es un detalle: es lo que hace que toda esta migración sea reversible y que **el sitio nunca se caiga por una tabla vacía**. Una base recién sembrada, un despliegue nuevo o una clave borrada por accidente dan exactamente el sitio de hoy, no una portada en blanco.

- **Cachea la lectura completa**, e **invalida la caché al guardar**. La portada la ve todo el mundo; no puede hacer veinte consultas por visita.
- **Las claves se agrupan por página y sección**, no sueltas: `inicio.servicios`, `nosotros.mision`, `contacto.telefono`. Un cajón plano de doscientas claves es ilegible a los tres meses.
- Expón lo que las páginas necesitan por las propiedades compartidas de Inertia, junto a `nodico`, **sin romper la forma actual de esa propiedad**: hay componentes que ya la leen.

---

# FASE 2 — Imágenes, que es donde esto se rompe

Léelo entero antes de escribir la primera línea. Aquí hay dos trampas que no se ven hasta que ya rompiste algo.

**Trampa 1: el despliegue pisa las imágenes.** `deploy_prueba.py` sincroniza `public/img` desde la máquina del programador hacia el servidor. Si el administrador sube una foto nueva y la guardas en `public/img/nodico/`, **el siguiente despliegue la sobrescribe con la del repositorio** y el trabajo del administrador desaparece sin aviso. Las imágenes subidas desde el panel tienen que vivir en una carpeta **que el despliegue no toque nunca**. Decide cuál, compruébalo leyendo `DIRS_ASSETS` y `DIRS_ESTATICOS` en `deploy_prueba.py`, y **déjalo escrito en `docs/DEPLOY.md`** para que el próximo despliegue no lo deshaga.

**Trampa 2: el disco `public` de Laravel necesita un enlace simbólico que aquí no existe.** No hay `public/storage`, el disco por defecto es `local`, y el servidor tiene un layout plano donde `public_html/` **es** el document root (ver `docs/DEPLOY.md`). Averigua qué funciona de verdad en ese servidor y **compruébalo con `curl` contra la URL pública**, no solo con `php artisan`. Si el enlace simbólico no es viable en Hostinger, dilo y propón la alternativa.

Y lo demás:

- **El sitio usa juegos de tres tamaños** (`foto.webp`, `foto-1280.webp`, `foto-640.webp`) con `srcset`. El administrador sube **un** archivo; **el servidor genera las variantes** y las convierte a WebP. Si le pides tres archivos a una persona no técnica, el módulo no se usa.
- **Cada hueco tiene su proporción** y no son iguales: el hero es vertical 9:16, `comunidad-fondo` es 2:1, las tarjetas de espacios son 4:3. Enséñale al administrador **la proporción esperada y una vista previa recortada antes de guardar**, o acabará subiendo fotos que se ven cortadas y no entenderá por qué.
- **Valida de verdad**: tipo real del archivo (no la extensión), tamaño máximo, dimensiones mínimas. Y avisa con claridad cuando una foto sea demasiado pequeña para el hueco.
- **Texto alternativo obligatorio** en cada imagen con contenido. Es accesibilidad, y el sitio ya lo trae bien puesto.
- Conserva la imagen anterior el tiempo suficiente para poder deshacer.

---

# FASE 3 — El módulo en el panel

Entrada **«Página Web»** en el menú, visible solo para Administración.

- **Organizado como el sitio, no como la base de datos.** El administrador piensa en «el inicio, la sección de servicios», no en `inicio.servicios.titulo`. Una pantalla por página pública, y dentro, las secciones en el mismo orden en que salen.
- **Cada campo dice dónde sale.** Un formulario de treinta campos sin contexto es inservible: junto a cada uno, en qué página y sección aparece.
- **Vista previa antes de publicar.** Que pueda ver el cambio sin que el público lo vea. Si una vista previa completa es mucho, al menos un enlace directo a la sección que tocó.
- **Guardar es explícito y por sección**, con aviso si intenta salirse con cambios sin guardar.
- **Deshacer.** Guarda las versiones anteriores de cada clave y deja volver a la última. Es la red de seguridad que hace que alguien se atreva a usar el módulo.
- **Responsive**, como el resto del panel: esto se va a usar desde el celular.

---

# FASE 4 — Migrar sección por sección

**Una sección por vez, y el sitio tiene que verse idéntico después de cada una.** Esa es la prueba de que la migración salió bien: si al mover `servicios` a la base de datos la portada cambia de aspecto, algo se perdió por el camino.

Orden sugerido, de menos a más riesgo:

1. **Datos de contacto y enlaces** — lo que más se pide cambiar y lo más aislado.
2. **Textos de secciones** — encabezados, descripciones, misión y visión.
3. **Los arreglos de contenido** — `servicios`, `espacios`, `beneficios`, `valores`, `incluidoEnTodas`, `comoFunciona`.
4. **Las imágenes.**
5. **Encender y apagar secciones.**

Cuando una sección ya se lee de la base, **quita el literal del componente y deja el respaldo en el servicio**, en un solo sitio. Que el mismo texto viva en dos lugares es garantía de que algún día discrepen.

---

# FASE 5 — Que no se pueda romper

Esto lo va a usar gente no técnica sobre la página que ve todo el mundo. La seguridad aquí no es solo de accesos:

- **Permiso nuevo** (`gestionar-sitio`), dentro del grupo `ADMINISTRACION`. Ni Staff ni Caja.
- **Nada de HTML libre.** El texto entra como texto. Si hace falta negrita o un enlace dentro de un párrafo, un subconjunto mínimo y **escapado al pintarlo**. Un campo que acepte HTML en el panel es una inyección de scripts servida en la portada.
- **Los enlaces se validan**: esquema `http`/`https`, y avisa si el destino no responde. Un enlace roto en el pie de página está roto para todos.
- **Longitudes máximas** por campo, pensadas para el diseño. Un título de trescientos caracteres rompe la maquetación aunque sea texto válido.
- **Todo cambio a la bitácora**: quién, cuándo, qué clave, valor anterior y nuevo.
- **Nada de esto puede tumbar la página pública.** Si una clave trae basura o el tipo no es el esperado, se registra el problema y **se sirve el respaldo**. La portada siempre responde.

---

# FASE 6 — Pruebas

- Con la tabla `ajustes` **vacía**, el sitio se ve exactamente igual que hoy. Ésta es la prueba más importante de todas.
- Guardar una clave cambia el sitio, y **deshacer lo devuelve**.
- Un valor corrupto en `ajustes` no rompe la página: se sirve el respaldo.
- Staff y Caja **no** alcanzan el módulo, ni por la pantalla ni llamando a la ruta directamente.
- Una imagen subida genera sus tres variantes y se sirve por HTTP con `curl`.
- **Una imagen subida sobrevive a un despliegue.** Despliega a pruebas después de subirla y confirma que sigue ahí.
- El texto con `<script>` dentro se pinta como texto, no se ejecuta.
- La caché se invalida al guardar: el cambio se ve sin esperar ni reiniciar nada.

---

## FORMA DE TRABAJAR

- **La Fase 0 primero y sola.** Enséñame `docs/CMS-PAGINA-WEB.md` y espera visto bueno antes de escribir código.
- Después, fase por fase, con el sitio público funcionando al final de cada una.
- **Ante la duda entre poder editar algo y que el sitio no se pueda romper, gana lo segundo.** Un campo menos se añade después; una portada rota la ve un cliente.
- **No dupliques contenido.** Cuando algo se mude a la base, desaparece del componente.
- Todo en español, con acentos correctos, y con el porqué escrito donde la decisión no sea obvia — como está el resto del proyecto.
