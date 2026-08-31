# Responsive — Nódico en iPhone y iPad

Registro del trabajo de responsive del panel y los portales. Una fila por pantalla:
qué se cambió, qué patrón se aplicó, y qué quedó pendiente con su razón.

Rama: `feature/responsive` (desde `feature/fase4`).

## Componentes nuevos (patrón compartido)

- **`Components/Panel/ListaResponsiva.vue`** — una sola fuente de datos, dos
  presentaciones: en `lg+` la tabla densa de siempre; por debajo de `lg`, cada
  fila se apila como tarjeta (identidad arriba · resumen visible · el resto tras
  «Ver detalle»). El corte es `lg` (no `sm`) a propósito: 744 px es la zona ciega
  donde la barra lateral ya se ocultó pero el contenido se compuso para escritorio.
  Recibe `columnas` (con rol `identidad`/`resumen`/`detalle`), `filas`, y opcional
  `href` (navega) o `seleccionable`+`@seleccionar` (abre un modal), y `filaClase`
  (resaltado por fila). El contenido de cada celda va por slot con ámbito `#[clave]`.
- **`Components/Panel/Paginacion.vue`** — la paginación del panel, una vez y a
  **44 px** (antes copiada en cuatro pantallas a 32 px).

## Fase 1.1 · Tablas ilegibles en móvil → tarjetas apiladas

| Pantalla | Cambio | Nota |
|---|---|---|
| `Miembros/Index` | `ListaResponsiva` + `Paginacion`. Identidad: nombre/empresa/Face ID · resumen: plan, estado · detalle: contacto, vence. | Navega a la ficha. Referencia del patrón. |
| `Salones/Index` | `ListaResponsiva` (`seleccionable`, abre el cotizador) + montaje/anticipo en detalle. | Encaja limpio (estilo Nódico). |
| `Facturas/Index` | `ListaResponsiva` + `Paginacion`. Datos fiscales en detalle. | Encaja limpio. |
| `Bitacora/Index` | Rama «acceso» a `ListaResponsiva` con `filaClase` (resalta accesos fallidos); rama «operación» ya era tarjetas. `Paginacion` en ambas. | Encaja limpio. |
| `Reportes/Index` | Solo la tabla «Miembros en riesgo». KPIs, barras y gráficas intactas. | Encaja limpio. Sin `href` (navega por el Link del nombre). |
| `DaypassInterior/Index` | Sustituido el doble marcado `hidden sm:table` + `ul sm:hidden` por `ListaResponsiva` (corte `sm`→`lg`). Resumen del mes, filtros, export y modal intactos. | Encaja limpio. |
| `Reservas/Index` | `ListaResponsiva` + `Paginacion`. Horario y estado visibles en la tarjeta. | ⚠️ Página en estilo Breeze viejo → choque visual (ver Deuda). |
| `Eventos/Index` | Solo la tabla «Eventos pasados» (las próximas ya eran tarjetas). | ⚠️ Página en estilo Breeze viejo (ver Deuda). |
| `Seguridad/Index` | Bitácora «Actividad de tu cuenta» a `ListaResponsiva`. | ⚠️ Pantalla del miembro en estilo Breeze viejo (ver Deuda). |
| `Dashboard` | No es tabla: la línea de tiempo de ocupación se reacomoda —nombre y estado arriba, barra a ancho completo debajo— por debajo de `lg`. Sin scroll horizontal. | Lenguaje propio del tablero, respetado. |

## Fase 1.2 · Paginación tocable

Extraída a `Paginacion.vue` a 44 px y aplicada en Miembros, Facturas, Bitácora y
Reservas (las que paginan).

## Fase 1.3 · Agenda con diseño propio de móvil

`Agenda/Index` — la retícula semanal (960 px) no cabe en un iPhone. Por debajo de
`lg` se muestra una **vista de un día, agrupada por espacio**: tira para elegir el
día de la semana, y por cada espacio sus reservas por hora + botón «Reservar». Usa
la misma semana que ya trae el servidor (sin pedir nada nuevo) y el mismo modal de
detalle. En `lg+`, la retícula intacta. (Decisión de Diego: agrupada por espacio,
no listado cronológico.)

## Fase 1.4 · Layouts del panel

- `AuthenticatedLayout` — la barra lateral de móvil pasó de `bg-dark` a `bg-tinta`
  (mismo color que la de escritorio; antes eran dos tonos para el mismo elemento).
- `AuthenticatedLayout` — el `<main>` ahora respeta el área segura del notch
  (`pb-segura`), como ya hacía `PortalLayout`. Es el panel que se usa en iPad/iPhone
  en el mostrador.
- `GuestLayout.vue` (Breeze, `bg-gray-100`) — **borrado**: ningún componente lo
  importaba (huérfano).

## Fase 1.6 · HeroVideo

Unidades unificadas a viewport pequeño: `min-h-[56.25vw]`/`min-w-[100vw]` →
`svw`. Antes mezclaba `svh` con `vw` y desbordaba en iPhone horizontal.

## Fase 1.7 · ContactSection

La franja de datos pasa de `grid-cols-2` fijo a `grid-cols-1 sm:grid-cols-2
lg:grid-cols-4`: en 375 px la dirección (larga) ya no se aprieta contra el horario.

## Fase 1.5 · Modales en móvil — PENDIENTE (bloque propio)

Plan (no empezado): la infraestructura ya existe en `composables/useBloqueoScroll.ts`
(contador compartido de bloqueo de scroll `bloquear`/`liberar`, `useInerteFuera`,
`atraparFoco`) — hoy solo la usan SiteHeader y HeroVideo. Falta:
1. Un componente `ModalHoja.vue`: hoja que sube desde abajo en móvil (con encabezado
   y acciones siempre visibles + scroll interno), centrado en `sm+`, estilo Nódico
   (no el `Modal.vue` de Breeze), que use el composable y cierre por botón/Esc/fondo.
2. Migrar los ~11 modales hoy inline (`fixed inset-0`): Agenda (3), Anuncios,
   Asesorías (Asesores/Index/Temas), DaypassInterior, Emprendedores, Facturas,
   Miembros/Show, Portal/MisReservas, Salones.
3. `Modal.vue` (Breeze) escribe `document.body.style.overflow` directo, sin el
   contador → migrar DeleteUserForm o alinear ese componente al composable.

## Deuda anotada (no es responsive — no se arregla en esta pasada)

- **Pantallas en estilo Breeze viejo** (`Reservas/Index`, `Eventos/Index`,
  `Seguridad/Index` del miembro): usan `bg-gray-*`, `rounded-2xl` y foco violeta,
  no el sistema Nódico. Al meter la tabla responsiva (estilo panel: cream/dark/mono)
  queda un choque visual. Migrarlas entera al sistema Nódico es un rediseño, fuera
  del alcance de responsive. **Pendiente de decisión de Diego.**
- **Botones de cabecera del `Panel`** (Reservar/Bloquear/Registrar) miden
  `min-h-[32px]`/`[36px]`: por debajo del mínimo táctil de 44 px. Se suben en la
  pasada de reglas globales (Fase 6).

## Pendiente (siguientes fases)

- Fase 1.4 layouts (unificar color de la barra lateral del panel, `pb-segura` en
  `AuthenticatedLayout`, decidir sobre `GuestLayout` de Breeze).
- Fase 1.5 modales como hoja inferior en móvil + contador compartido de bloqueo de scroll.
- Fase 1.6 `HeroVideo` (unidades mezcladas). Fase 1.7 `ContactSection` (2 columnas apretadas).
- Fases 2–5 (público, auth, portal, operativo) y 6 (reglas globales).

## Limitaciones de verificación

- La captura visual por debajo de ~768 px no se pudo hacer con el toolset de
  navegador (no baja el viewport de captura) y el panel exige 2FA para entrar. La
  verificación se hizo por código + build + previsualización fiel con las clases
  reales. El visto bueno visual en iPhone/iPad real queda del lado de Diego.
