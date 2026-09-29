# Prompt para Claude Code — Publicar las fotos nuevas y la sección Espacios en prueba.nodico.com.mx

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## QUÉ HAY QUE PUBLICAR

En la rama **`feature/pagos-bbva`** (la actual) hay trabajo sin confirmar que todavía no se ve en el servidor de pruebas:

**Fotos reemplazadas** (las anteriores quedaron respaldadas en `recursos/fotos-anteriores/`):

- `comunidad-fondo` — **era una foto de banco de imágenes**, ajena a Nódico. Se usa en la portada de Comunidad, en el inicio y **de fondo en las pantallas de login y registro**.
- `mision` y `vision` — eran salones vacíos; ahora son el espacio con gente dentro.

**Fotos nuevas** para la sección Espacios: `espacio-cubiculos`, `espacio-salas-juntas`, `espacio-contenido`, `espacio-fotografia` (cada una con su variante `-800`), y `sala-contenido` en tres tamaños.

**`resources/js/Pages/Welcome.vue`** — sección **Espacios** nueva entre Servicios y Beneficios (cuatro tarjetas con foto, cantidad y capacidad), y corrección de la tarjeta «Sala profesional de creación de contenido», que prometía un estudio de grabación y enseñaba el área de trabajo abierta.

**Correo saliente** — `app/Console/Commands/ProbarCorreo.php`, `configurar-correo.bat` y `configurar-correo.ps1`.

---

## ANTES DE NADA: DIME QUÉ VA A SALIR

`deploy_prueba.py` publica **el árbol de trabajo completo**, no una selección. La rama actual `feature/pagos-bbva` tiene **18 commits que `feature/integracion-staging` no tiene**, incluidos los tres de la pasarela de pagos:

```
56d0456 feat(pagos): pasarela intercambiable Stripe/BBVA con cobro por formulario del banco
622cccc fix(pagos): a BBVA se le manda REMOTE_ADDR, no $request->ip()
b96a1cc docs(pagos): resultado de la IP del cliente en Hostinger
```

Antes de desplegar, **enséñame la lista de lo que va a salir** (`git log --oneline feature/integracion-staging..HEAD`) y dime en una línea si alguno de esos commits puede romper el cobro en el servidor de pruebas. Si la pasarela quedó a medias, dilo y espera: se saca la parte visual aparte en vez de publicar un cobro roto.

---

# PASO 1 — Dejar el árbol limpio

`deploy_prueba.py` **se niega a desplegar con cambios sin confirmar** (comprobación OPS-01), y con razón: lo que quedara en el servidor no correspondería a ningún commit y después no habría forma de saber qué se publicó.

Antes de confirmar nada:

- **`recursos/fotos-anteriores/` NO se commitea.** Son las fotos que se reemplazaron, guardadas por si hay que volver atrás. Añádelo a `.gitignore`, junto a la línea que ya existe para `/recursos/fuentes-originales/`.
- Revisa `git status` completo y dime si hay algo que no reconozcas. `app-movil/app.json` aparece modificado y **no es mío**: mira qué cambió antes de arrastrarlo.

Después, commits separados y con el porqué, no un cajón de sastre:

1. Las fotos (las tres reemplazadas y las nuevas de espacios).
2. `Welcome.vue` con la sección Espacios y el arreglo de la tarjeta de contenido.
3. El correo saliente (`ProbarCorreo.php` y los dos scripts).

---

# PASO 2 — Compilar los assets en local

**El servidor no tiene Node.** Los assets se compilan aquí y se suben ya construidos:

```bash
npm run build
```

`Welcome.vue` cambió, así que este paso **no es opcional**: sin él, el servidor sigue sirviendo el JavaScript viejo y la sección Espacios no aparece aunque las imágenes sí suban.

Si `npm run build` falla, **para y enséñame el error**. No despliegues con un build a medias.

---

# PASO 3 — Desplegar

```bash
python deploy_prueba.py
```

El script ya sabe lo que hay que saber de este servidor; no lo reinventes ni subas archivos a mano por SFTP:

- Sincroniza `public/img` a **la raíz** de `public_html/img`, que es donde este host sirve los estáticos. Las fotos nuevas entran por ahí.
- Sube los assets a **las dos rutas** que este servidor necesita, `public_html/build/` y `public_html/public/build/`. Si se desincronizan, Laravel apunta a archivos que no existen (comprobación OPS-02).
- Corre `composer install`, `migrate --force`, `db:seed --class=NodicoWebSeeder` y recalienta las cachés.

**No uses `--sucio`.** Existe para una urgencia y ésta no lo es.

---

# PASO 4 — Verificar en la URL real, no por SSH

**Que `php artisan` funcione por SSH no prueba que la web funcione**: el CLI corre PHP 8.2 y el sitio corre sobre PHP-FPM 8.3. Comprueba siempre contra la URL.

```bash
# 1. Qué quedó publicado (tiene que ser el commit que acabas de hacer)
curl -s https://prueba.nodico.com.mx/build/version.json

# 2. Las páginas siguen en pie
for r in / /nosotros /membresias /eventos /actividades; do
  echo "$r -> $(curl -s -o /dev/null -w '%{http_code}' https://prueba.nodico.com.mx$r)"
done

# 3. Las fotos nuevas de verdad llegaron (200 y con peso, no 404 ni 0 bytes)
for f in espacio-cubiculos espacio-salas-juntas espacio-contenido espacio-fotografia \
         sala-contenido comunidad-fondo mision vision; do
  echo "$f -> $(curl -s -o /dev/null -w '%{http_code} %{size_download}' \
    https://prueba.nodico.com.mx/img/nodico/$f.webp)"
done

# 4. Sigue cerrado a buscadores
curl -s https://prueba.nodico.com.mx/robots.txt   # debe decir "Disallow: /"
```

Y **míralo con ojos, no solo con curl**, que es donde se cae este tipo de cambio:

- La sección **Espacios** aparece entre Servicios y Beneficios, con las cuatro tarjetas y sus fotos.
- La tarjeta «Sala profesional de creación de contenido» ya enseña **la cabina de podcast**, no el área de trabajo abierta.
- El fondo de **login y registro** ya no es la señora genérica de camisa blanca.
- En **Nosotros**, el texto de la Visión se sigue leyendo bien sobre la foto nueva (lleva una capa oscura al 88%; la foto cambió, la capa no).
- Todo lo anterior **en celular**, no solo en el escritorio.

---

# SI ALGO SALE MAL

- **Las imágenes dan 404:** no llegaron a `public_html/img/nodico/`. Comprueba con `ssh ... ls` esa carpeta antes de volver a subir. No las copies a `public_html/public/img/`: en este host los estáticos van a la raíz.
- **La sección no aparece pero las fotos sí:** falta el `npm run build`, o los dos manifiestos quedaron desincronizados. Compara los `md5sum` de `build/manifest.json` en las dos rutas.
- **`version.json` trae un commit viejo:** el despliegue no llegó a subir el build. Repite; no supongas.
- **Para volver atrás:** las fotos anteriores están en `recursos/fotos-anteriores/`. Restaurarlas y volver a desplegar es suficiente; no hay nada en base de datos que dependa de esto.

---

## FORMA DE TRABAJAR

- **Enséñame la lista de commits que van a salir antes de desplegar**, y espera visto bueno.
- Un paso a la vez, comprobando entre uno y otro. Si algo falla, para y dime — no encadenes despliegues encima de un error.
- No toques `deploy_prueba.py` ni `deploy.sh` para que «funcione»: si el despliegue se queja, casi siempre tiene razón.
- Todo en español, con acentos correctos.
