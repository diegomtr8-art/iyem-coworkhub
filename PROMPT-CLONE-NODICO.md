# Prompt para Claude Code — Clonar nodico.com.mx en el proyecto y desplegar a prueba.nodico.com.mx

> Copia todo lo que está debajo de la línea y pégalo en Claude Code, dentro del proyecto `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Soy el área de informática del **Instituto Yucateco de Emprendedores (IYEM)**. **Nódico** es marca registrada del IYEM, por lo que el sitio `https://www.nodico.com.mx/` es propiedad nuestra. Actualmente corre en **Odoo** y quiero migrarlo a este proyecto Laravel para tener control total del código.

**Objetivo:** reconstruir la vista pública del sitio, fiel al diseño actual, dentro de este proyecto, y desplegarla en `https://www.prueba.nodico.com.mx` como ambiente de staging.

## STACK EXISTENTE (no lo cambies)

- Laravel 12 + Inertia.js 2 + Vue 3 (Composition API, `<script setup>`)
- Tailwind CSS 3 + Vite 7
- `lenis` (scroll suave), `lucide-vue-next` (iconos), `vue-sonner` (toasts) ya instalados
- Repo: `https://github.com/diegomtr8-art/iyem-coworkhub.git`, rama `main`

### Rutas públicas ya definidas en `routes/web.php`

| Ruta | Controlador | Página Inertia | Equivalente en Odoo |
|---|---|---|---|
| `/` | `WelcomeController@index` | `Welcome.vue` | `/` |
| `/nosotros` | `WelcomeController@nosotros` | `Nosotros.vue` | `/nosotros` |
| `/membresias` | `WelcomeController@membresias` | `Membresias.vue` | `/membresias` |
| `/eventos` | `WelcomeController@eventos` | `Eventos.vue` | `/salones` |
| `/actividades` | `WelcomeController@eventos` | `Eventos.vue` | `/comunidad` |
| `POST /contacto` | `ContactoController@store` | — | form "Hablemos" |

**Importante:** en Odoo, `/salones` (Eventos) y `/comunidad` (Actividades) son **dos páginas distintas** con contenido diferente, pero aquí ambas apuntan al mismo método. Sepáralas: crea `WelcomeController@salones` → `Salones.vue` para `/eventos`, y deja `WelcomeController@comunidad` → `Comunidad.vue` para `/actividades`. Conserva los nombres de ruta actuales (`eventos`, `actividades`) para no romper enlaces.

### Design system ya configurado en `tailwind.config.js` — ÚSALO, no inventes colores

```
nodo.400   #FFE124   amarillo Nódico (color de marca principal)
dark       #2E2D2C   fondo oscuro / nav
cream      #F4F1EA   fondo claro
plan.flex  #FFDD00 · plan.pro #D6E265 · plan.daypass #EF7E88 · plan.match #864B95
```

Tipografías (ya en `public/fonts/`): **Carmen Sans** (`font-display`, títulos) y **GT Eesti Pro Display** (`font-body`, texto). Si faltan `@font-face`, agrégalos en `resources/css/app.css`. En `nodico/PAGINA WEB NÓDICO/` están los assets originales de marca (iconos, logo, PDF del diseño) — úsalos.

---

## TAREA 1 — Auditar el sitio original

Antes de escribir código, visita y extrae de cada página el texto exacto, la jerarquía de secciones y las URLs de imágenes:

- `https://www.nodico.com.mx/`
- `https://www.nodico.com.mx/nosotros`
- `https://www.nodico.com.mx/membresias`
- `https://www.nodico.com.mx/salones`
- `https://www.nodico.com.mx/comunidad`

Descarga todas las imágenes (`/web/image/...`, formatos `.webp`, `.png`, `.svg`) a `public/img/nodico/` con nombres legibles en kebab-case (ej. `hero-inicio.webp`, `icono-wifi.svg`, `salon-yucatan-emprende-1.webp`). **No dejes ninguna URL apuntando a nodico.com.mx en producción.**

Guarda un `docs/AUDITORIA-NODICO.md` con el inventario: sección → texto → imagen local correspondiente.

---

## TAREA 2 — Componentes compartidos

Crea en `resources/js/Components/Public/`:

- **`PublicLayout.vue`** — layout de todas las páginas públicas (`resources/js/Layouts/PublicLayout.vue`), con slot principal.
- **`SiteHeader.vue`** — nav sticky con logo Nódico, enlaces Inicio / Nosotros / Membresías / Eventos / Actividades, menú hamburguesa responsive. Usa `<Link>` de Inertia y marca el activo. **Omite** los iconos de carrito/wishlist de Odoo (no hay e-commerce).
- **`SiteFooter.vue`** — logo blanco, columna de navegación, "Síganos" con Instagram / Facebook / LinkedIn, aviso de facturación a `contacto@nodico.com.mx` con asunto "Solicitud de factura", y la leyenda de marca registrada del IYEM.
- **`ContactSection.vue`** — sección "Hablemos" con campos Nombre, Teléfono, E-mail, Su empresa, Asunto, Comentarios. Envía por `useForm` de Inertia a `route('contacto.store')`, con validación, estado de carga y toast de éxito/error vía `vue-sonner`.
- **`SectionHeading.vue`**, **`IconCard.vue`**, **`PlanCard.vue`**, **`ScrollReveal.vue`** (animación de entrada al hacer scroll, con `IntersectionObserver`).

Inicializa `lenis` una sola vez en el layout, y respeta `prefers-reduced-motion`.

---

## TAREA 3 — Páginas

Reproduce la estructura y el contenido de cada página original. Usa el texto exacto que extrajiste en la Tarea 1.

### `Welcome.vue` (`/`)
1. Hero: "Bienvenidos al lugar / Donde el trabajo es un pretexto para crear" + CTA "Conocer más" → `/nosotros`, sobre fondo amarillo Nódico con foto.
2. **Servicios** — grid de 6 tarjetas con icono SVG: espacio colaborativo, wifi 200 MB, sala de creación de contenido, recepción de paquetería, hasta 5 invitados gratuitos al mes, café y agua todo el día.
3. **Beneficios adicionales** — grid de 5: descuentos Tienda Herencia Viva, directorio de miembros, acceso preferente a eventos y talleres, conexión con el ecosistema emprendedor, espacio pet friendly.
4. **Elige tu plan ideal** — 4 tarjetas (Nódico Flex, Nodo Pro, Day-Pass, Nodo Match) alimentadas por el prop `planes` que ya envía el controlador; cada una con su color de `plan.*` y enlace a `/membresias`. Si `planes` viene vacío, usa un fallback estático.
5. Banner day-pass gratuito para emprendedores y artesanos del interior del estado.
6. **¿Quieres conocer más de Nódico?** — logos aliados: IYEM (`https://iyem.yucatan.gob.mx/`), Herencia Viva (`http://www.herenciaviva.com`), CANIETI (`https://canieti.org`).
7. Teaser de **Salones para eventos** → `/eventos`.
8. `<ContactSection />`.

### `Nosotros.vue` (`/nosotros`)
1. Hero "¿Quiénes somos?" con el párrafo de comunidad profesional + CTA "Conocer más" → `/actividades`.
2. **Nuestra Misión** y **Nuestra Visión** — dos bloques alternados imagen/texto.
3. **Nuestros Valores** — grid de 6 con iconos: Creatividad, Colaboración, Innovación, Diversidad e inclusión, Democratización del acceso a espacios de calidad, Comunidad.
4. `<ContactSection />`.

### `Membresias.vue` (`/membresias`)
Encabezado "Precios competitivos" y 4 planes con su lista de beneficios. Los precios y enlaces de pago deben venir de la BD (`Plane`), **no hardcodeados** — agrega a la migración/seeder los campos `stripe_url` y `beneficios` (JSON) si no existen, y siembra estos datos como valores iniciales:

| Plan | Precio | Periodo | Checkout Stripe |
|---|---|---|---|
| Day-Pass | $79.00 | 1 día | `https://buy.stripe.com/00waER7JU4Lp65v0gb6Zy05` |
| Nódico Flex | $249.00 | 4 días | `https://buy.stripe.com/6oUdR36FQ0v951r4wr6Zy04` |
| Nodo Pro | $599.00 | mensual | `https://buy.stripe.com/6oU8wJggq91FeC1e716Zy03` |
| Nodo Match (2 pax) | $799.00 | 1 mes | `https://buy.stripe.com/28EcMZc0agu73Xn6Ez6Zy02` |

Copia los beneficios exactos de cada plan desde la página original. Botones "Empezar Ahora" / "Contrata Ahora" con `target="_blank" rel="noopener"`. Cierra con `<ContactSection />`.

### `Salones.vue` (`/eventos`)
1. Hero "Conoce nuestros Salones para eventos" sobre el fondo amarillo.
2. Tarjetas de **Yucatán Emprende 1** y **Yucatán Emprende 2**: descripción, lista "Incluye" (proyector, sonido, internet, sillas, mesas, manteles, base de micrófono, extensiones, adaptador HDMI, mesa de registro, pódium) y ficha técnica (medidas 15x14 m, $600 mxn/hora, capacidad, herradura, mesas de trabajo, escuela, auditorio).
3. Nota de Coffee Break (25 pax $45.00 MXN p/p; desde 100 pax $35.00 MXN p/p).
4. Alimenta las salas desde el modelo `Espacio` si ya tiene los campos; si no, agrégalos al seeder.
5. `<ContactSection />`.

> El original repite "Yucatán Emprende 1" dos veces con capacidades distintas (120 y 150). Corrígelo: deja **Yucatán Emprende 1 = 120 pax** y **Yucatán Emprende 2 = 120 pax**, y anótalo en `docs/AUDITORIA-NODICO.md` como discrepancia detectada para que la validemos.

### `Comunidad.vue` (`/actividades`)
1. "Nuestro contenido más reciente" — usa el prop `proximos` / `pasados` de eventos que ya envía el controlador.
2. **Talleres del mes** — embebe el calendario de Luma: `https://luma.com/embed/calendar/cal-ZE3dbDW6bLs4v7j/events?lt=dark` en un `<iframe>` responsivo (uno solo, el original lo duplica por error).
3. **Emprendedor de la semana** — bloque destacado (contenido actual: Salabtún, sal artesanal de Celestún).
4. **Directorio** — grid de emprendedores con foto y enlace a Instagram: Ahimsa Daram (`ahimsadaram`), Zentto (`zentto.mid`), SaboReli (`saborelimx`), Kinimitas (`kinimitas`).
5. Teaser de salones → `/eventos`.
6. `<ContactSection />`.

---

## TAREA 4 — Backend

1. Añade `salones()` y `comunidad()` a `WelcomeController` y actualiza `routes/web.php`.
2. Verifica que `ContactoController@store` valide y envíe correo a `contacto@nodico.com.mx`; si no existe el Mailable, créalo (`app/Mail/ContactoRecibido.php` + vista Blade) y guarda también el registro en BD.
3. Agrega los campos y seeders necesarios para planes y espacios.
4. Migraciones nuevas, nunca edites migraciones ya ejecutadas.

## TAREA 5 — Calidad

- **Responsive real** en 375 / 768 / 1024 / 1440 px.
- **SEO**: `<Head>` de Inertia por página con `title` y `meta description` propios (los del original están vacíos o genéricos — escribe descripciones reales). Elimina el `noindex` que trae Odoo del home, pero **en staging sí deja `noindex`** (ver Tarea 6).
- **Accesibilidad**: `alt` descriptivo en toda imagen, jerarquía correcta de encabezados (un solo `<h1>` por página), foco visible, contraste AA.
- **Rendimiento**: imágenes en `.webp`, `loading="lazy"` salvo el hero, `width`/`height` explícitos.
- Ejecuta `npm run build` y `php artisan test` sin errores antes de dar por terminado.

## TAREA 6 — Despliegue a www.prueba.nodico.com.mx (VPS por SSH)

1. Crea `deploy.sh` en la raíz del repo:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/prueba.nodico.com.mx          # ajusta la ruta real
php artisan down --render="errors::503" || true
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true
php artisan up
```

2. Crea `docs/DEPLOY.md` con el procedimiento completo:
   - Registro DNS `A` para `prueba.nodico.com.mx` y `www.prueba.nodico.com.mx`.
   - Virtual host de Nginx/Apache apuntando a `public/`.
   - Certificado SSL con Certbot para ambos nombres.
   - `.env` de staging: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://www.prueba.nodico.com.mx`, credenciales de BD y de correo propias de staging. **Nunca subas `.env` al repo.**
   - Permisos: `storage/` y `bootstrap/cache/` escribibles por el usuario del servidor web.
3. Agrega un `public/robots.txt` (o middleware) que bloquee indexación **solo cuando `APP_ENV=staging`**, para que Google no indexe el ambiente de prueba.
4. Añade un banner discreto "AMBIENTE DE PRUEBA" visible únicamente en staging.

**No ejecutes el deploy tú mismo.** Prepara todo, y al final dime exactamente qué comandos debo correr yo en el servidor y qué datos me faltan (host SSH, usuario, ruta, credenciales DNS).

---

## FORMA DE TRABAJAR

- Trabaja en una rama `feature/clone-sitio-publico`, con commits pequeños y descriptivos en español.
- Antes de empezar, muéstrame tu plan de archivos a crear/modificar y espera mi visto bueno.
- Ve página por página: termina Inicio, muéstrame el resultado, y hasta que lo apruebe sigue con la siguiente.
- No toques nada de `/dashboard`, `/portal` ni el módulo de autenticación.
- Todo el texto de la interfaz en español, con acentos correctos.
- Si algo del original es ambiguo o parece un error, **no lo adivines**: anótalo en `docs/AUDITORIA-NODICO.md` y pregúntame.
