# Prompt para Claude Code — Nódico completo en iPhone y iPad

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico es el coworking del Instituto Yucateco de Emprendedores. El sistema tiene cuatro zonas construidas y funcionando en `prueba.nodico.com.mx`, pero solo el sitio público se probó en serio en móvil. Este trabajo es únicamente eso: **que las cuatro zonas se vean y se usen bien en iPhone y en iPad.**

Sin funcionalidad nueva. Sin rediseños. Si algo se ve mal, se arregla; si se ve bien, no se toca.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4 · Vite 7. Sistema de diseño «editorial técnico» en `.claude/skills/nodico-design/SKILL.md` — respétalo, no inventes tokens nuevos.

**Rama:** `feature/responsive`, a partir de la rama integrada más reciente.

### El alcance real: unas 45 pantallas

| Zona | Pantallas |
|---|---|
| Sitio público | Portada, Nosotros, Membresías, Salones, Comunidad, documentos legales, error 403 |
| Autenticación | 12 pantallas: acceso, registro, recuperación, restablecer, verificar correo, confirmar contraseña, desafío y alta de segundo factor, enlace mágico, consentimiento, cuenta suspendida |
| Portal del miembro | 10 pantallas: inicio, perfil, seguridad, datos fiscales, membresía, reservar, mis reservas, asesoría, accesos, pagos |
| Portal operativo | Tablero más 16 secciones: agenda, anuncios, asesorías (con temas y asesores), bitácora, check-ins, day-pass del interior, emprendedores, espacios, eventos, facturas, miembros, planes, reportes, reservas, salones, seguridad |

### Anchos obligatorios

| Dispositivo | Ancho | Por qué importa |
|---|---|---|
| iPhone SE | 375 px | El piso. Si funciona aquí, funciona en todo lo demás. |
| iPhone 15 | 393 px | El más común hoy. |
| iPhone 15 Pro Max | 430 px | |
| iPhone horizontal | 852 × 393 px | Donde se rompen los bloques a pantalla completa. |
| iPad mini vertical | 744 px | Justo debajo del corte `lg` de Tailwind: la zona ciega. |
| iPad Pro vertical | 1024 px | El corte `lg`. Aquí cambia el layout del panel. |
| iPad Pro horizontal | 1366 px | Como se usará en el mostrador. |

**Los dos anchos donde más se rompen las cosas son 375 px y 744 px.** El segundo porque cae entre `md` y `lg`: la barra lateral ya se escondió pero el contenido todavía se compuso pensando en escritorio.

---

# FASE 1 — Lo que ya sabemos que está mal

No hace falta buscarlo: ya está localizado. Empieza por aquí.

## 1.1 · Diez tablas que en un iPhone son ilegibles

Todas tienen `overflow-x-auto`, así que **nada «se rompe»** — y por eso nadie lo ha notado. Pero leer siete columnas arrastrando el dedo en una pantalla de 375 px no es usable. El scroll horizontal es una tirita, no una solución.

| Archivo | Ancho mínimo forzado | Columnas |
|---|---|---|
| `Pages/Agenda/Index.vue` | `min-w-[60rem]` = **960 px** | 4 |
| `Pages/Facturas/Index.vue` | `min-w-[52rem]` = 832 px | 7 |
| `Pages/Salones/Index.vue` | `min-w-[52rem]` = 832 px | 7 |
| `Pages/Bitacora/Index.vue` | `min-w-[46rem]` = 736 px | 5 |
| `Pages/Miembros/Index.vue` | `min-w-[44rem]` = 704 px | 7 |
| `Pages/Reservas/Index.vue` | `min-w-[700px]` | 7 |
| `Pages/Reportes/Index.vue` | `min-w-[40rem]` = 640 px | 6 |
| `Pages/Dashboard.vue` | `min-w-[42rem]` = 672 px (lista) | — |
| `Pages/DaypassInterior/Index.vue` | sin mínimo | 3 |
| `Pages/Eventos/Index.vue` | sin mínimo | 5 |
| `Pages/Seguridad/Index.vue` | sin mínimo | 4 |

**El patrón a aplicar:** por debajo de `lg`, cada fila se convierte en una **tarjeta apilada**. Arriba lo que identifica el registro (nombre del miembro, folio de factura, espacio reservado); debajo, los dos o tres datos que de verdad se consultan en movimiento; el resto, escondido tras «Ver detalle». A partir de `lg`, la tabla vuelve como está hoy.

**Una misma fuente de datos, dos presentaciones.** No dupliques el marcado a mano en cada archivo: construye un componente de lista responsiva —recibe columnas y decide sola— y úsalo en las diez. Si acabas escribiendo la misma estructura once veces, te equivocaste de camino.

**Decide qué se ve en móvil pensando en quién lo usa.** Recepción en el mostrador con alguien enfrente necesita ver el nombre y el estado, no la fecha de creación ni el identificador.

## 1.2 · La paginación no se puede tocar

Los botones de página miden `min-h-[32px] min-w-[32px]`. El mínimo aceptable es 44 px, y estos están un 27 % por debajo. Aparece en `Asesorias/Index.vue`, `Bitacora/Index.vue`, `Facturas/Index.vue` y `Miembros/Index.vue`.

Súbelos a 44 px y, ya que vas, sácalos a un componente único: hoy el mismo bloque está copiado en cuatro archivos.

## 1.3 · La agenda semanal no cabe, y no puede caber

`Agenda/Index.vue` es una línea de tiempo de 960 px de ancho mínimo. En un iPhone no hay forma de comprimirla sin volverla inútil: es la pantalla que **necesita un diseño propio para móvil**, no un ajuste.

- **iPad y escritorio:** la línea de tiempo actual, que ahí funciona bien.
- **iPhone:** vista de un solo día, con selector de fecha arriba y las reservas de ese día como lista ordenada por hora, mostrando espacio, horario y quién reservó. Navegación día anterior / día siguiente.

Es la pantalla más usada del panel. Vale la pena el trabajo extra.

## 1.4 · Los dos layouts del panel no se comportan igual

- `AuthenticatedLayout.vue` (operativo) pinta la barra lateral de escritorio con `bg-tinta` y la de móvil con `bg-dark`: son dos colores distintos para el mismo elemento. Unifica.
- **`AuthenticatedLayout.vue` no respeta el área segura del notch**, mientras que `PortalLayout.vue` sí usa `pb-segura`. El panel operativo es justamente el que se va a usar en iPad y iPhone en el mostrador. Corrígelo.
- `GuestLayout.vue` sigue siendo el de Laravel Breeze, con `bg-gray-100` y nada del sistema de Nódico. Averigua si algo lo sigue usando: si no, bórralo; si sí, esa pantalla está fuera del diseño y hay que traerla.

## 1.5 · Los modales en móvil

Dieciséis archivos abren superposiciones con `fixed inset-0`. En móvil, un modal con formulario largo se queda sin espacio y a veces sin salida visible.

- Por debajo de `lg`: hoja que sube desde abajo o pantalla completa, con el encabezado y el botón de acción siempre visibles, y el contenido con su propio scroll interno.
- Bloqueo de fondo con **contador compartido**: ya hubo un caso de dos componentes peleándose por `document.body.style.overflow` y dejando la página trabada. Si ese contador no existe todavía, créalo y úsalo en todos.
- Cierre siempre alcanzable: botón visible, `Esc`, y toque en el fondo real.

## 1.6 · El video del hero en iPhone horizontal

`HeroVideo.vue` mezcla unidades: `w-[177.78svh]` junto a `min-w-[100vw]`. En iPhone horizontal esa combinación desborda. Unifica a unidades de viewport pequeño (`svh` / `svw`) y comprueba el recorte en 852 × 393 px, que es donde se ve.

## 1.7 · La franja de contacto

`ContactSection.vue` pone los cuatro datos en `grid-cols-2` en móvil. La dirección es larga y se aprieta contra el horario. En 375 px va en una sola columna.

---

# FASE 2 — Sitio público

Ya se revisó una vez, así que aquí toca confirmar y afinar, no rehacer:

- Portada: hero, servicios en mosaico, paneles de beneficios, carrusel de membresías, day-pass, salones, aliados, Instagram y contacto.
- Nosotros, Membresías, Salones, Comunidad, documentos legales y la página de error 403.
- El menú móvil a pantalla completa: que atrape el foco, bloquee el fondo y cierre bien.
- El carrusel de membresías con el dedo: que el arrastre no se coma los toques al botón de cada plan.
- Los paneles de beneficios en táctil: ahí el hover no existe, tienen que funcionar por toque.

---

# FASE 3 — Autenticación

Doce pantallas. El layout parte en dos columnas a partir de `lg`; por debajo, el panel de marca se reduce a una banda superior.

- Que esa banda no se coma media pantalla en iPhone horizontal, donde solo hay 393 px de alto.
- **Todos los campos con al menos 16 px de tamaño de letra.** Por debajo de eso, iOS hace zoom automático al enfocar y descuadra la pantalla. Verifícalo campo por campo, no por encima: `@tailwindcss/forms` ya dio problemas antes con esto.
- `autocomplete` correcto en cada campo para que funcionen el llavero de iCloud y el autorrelleno.
- El desafío de segundo factor: seis casillas que en 375 px sigan siendo tocables, con avance automático y pegado desde el portapapeles.
- El teclado en pantalla no debe tapar el botón de envío.

---

# FASE 4 — Portal del miembro

Diez pantallas. `PortalLayout` ya trae barra lateral en escritorio y menú deslizante en móvil; confirma que el deslizante cierre al navegar y no deje el fondo desplazable.

Lo delicado está en tres pantallas:

**Reservar.** Es la que más se va a usar desde el teléfono y la más compleja. Tiene un calendario de siete columnas y una reja de horarios que pasa de cuatro a ocho columnas. En 375 px, cada día del calendario y cada bloque de hora tienen que ser cómodos de tocar — si quedan por debajo de 44 px, replantea la reja en vez de encoger. Comprueba el recorrido completo con el pulgar: elegir espacio, día, horario, revisar el resumen y confirmar.

**Inicio.** Los medidores de bolsas de horas pasan a una columna en iPhone sin perder legibilidad. La reja va de cuatro columnas a dos, y a una.

**Mis reservas.** El aviso de si cancelar devuelve o no las horas tiene que leerse **antes** de pulsar, también en pantalla chica. Nadie debe descubrir la penalización después.

---

# FASE 5 — Portal operativo

La zona con más trabajo, y la que más se va a usar en iPad. Aplica el patrón de la Fase 1.1 y revisa además:

- **Formularios largos** (planes, espacios, eventos, salones): campos a una columna en móvil, con el botón de guardar siempre alcanzable — fijo abajo si el formulario es largo.
- **Filtros y buscadores**: en móvil se pliegan tras un botón «Filtrar» en vez de ocupar media pantalla antes de los resultados.
- **Fichas de detalle** (`Miembros/Show.vue`): mucha información en columnas; apílala por bloques con títulos claros.
- **Check-in**: la pantalla de mostrador. Tiene que ser cómoda con una mano — buscar, un toque para entrada, un toque para salida.
- **Reportes**: si hay gráficas, que escalen o se sustituyan por su tabla resumida en móvil. Una gráfica ilegible es peor que un número.
- **Encabezados y filtros pegajosos** en listas largas, para no perder el contexto al desplazar.

---

# FASE 6 — Reglas para todo el proyecto

No negociables, en las cuatro zonas:

1. **Cero scroll horizontal del cuerpo de la página** en cualquier ancho. El contenido ancho scrollea dentro de su propio contenedor, nunca arrastra la página.
2. **Área táctil de 44 × 44 px** en todo lo pulsable.
3. **Campos de formulario de 16 px o más**, siempre.
4. **Área segura del notch** respetada arriba y abajo, en las cuatro zonas.
5. **Imágenes con proporción reservada** para que no salte el layout al cargar.
6. **`prefers-reduced-motion`** respetado en toda animación nueva.
7. **Foco visible** en todo elemento alcanzable con teclado — aplica también en iPad con teclado, que es como se usará el panel.
8. **Nada dependiente solo del hover.** En táctil no existe: todo lo que se revela al pasar el cursor debe tener equivalente por toque.

---

# FASE 7 — Verificación con evidencia

Que quede constancia, no una afirmación.

1. Recorre **las siete medidas** en cada una de las ~45 pantallas, en el navegador.
2. **Captura de cada pantalla en 375 px y en 744 px.** Revísalas tú mismo y corrige antes de enseñármelas: si en una captura algo se ve apretado, cortado o encimado, no me la muestres — arréglala.
3. Verifica que ningún cuerpo de página tenga scroll horizontal, comprobándolo de forma automatizada en todas las rutas, no a ojo.
4. Recorrido completo con teclado en cada zona.
5. `npm run build` y `php artisan test` sin errores. Consola del navegador limpia.
6. Lighthouse móvil en la portada y en una pantalla de cada portal.
7. Escribe `docs/RESPONSIVE.md`: qué se cambió por pantalla, qué patrón se aplicó, y qué quedó pendiente con su razón.
8. `/security-review` sobre el diff. Aunque sea trabajo de presentación, se tocan muchos archivos.

---

# FASE 8 — Despliegue

Sigue `docs/DEPLOY.md`. Hostinger no tiene Node —los assets se compilan en local— y hay que sincronizar `public_html/build/` y `public_html/public/build/`.

Después de desplegar, **abre `prueba.nodico.com.mx` en un iPhone y en un iPad de verdad**, no solo en el emulador del navegador. El teclado en pantalla, el rebote del scroll de iOS, la barra de direcciones que aparece y desaparece y el comportamiento del notch no se reproducen fielmente emulando. Recorre al menos: contratar desde la landing, entrar, reservar una sala desde el portal, y hacer un check-in desde el panel.

---

## FORMA DE TRABAJAR

- Empieza por la Fase 1: son problemas ya localizados, con archivo y valor concreto.
- Antes de tocar las diez tablas, **muéstrame el componente de lista responsiva** aplicado a una sola —`Miembros/Index.vue`— y espera mi visto bueno. Si el patrón sirve ahí, sirve en las demás.
- Después avanza zona por zona y enséñame las capturas al terminar cada una.
- **No cambies funcionalidad ni rediseñes.** Si te encuentras algo roto que no es de responsive, anótalo en `docs/RESPONSIVE.md` y sigue; no lo arregles aquí.
- Si una pantalla necesita un diseño distinto en móvil y no solo un ajuste —como la agenda semanal—, dímelo antes de construirlo.
- Todo en español, con acentos correctos.
