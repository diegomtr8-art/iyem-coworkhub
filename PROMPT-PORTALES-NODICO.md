# Prompt para Claude Code — Portal del miembro y portal operativo de Nódico

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida. El sitio público ya está construido y la autenticación está en marcha. Toca ahora el corazón del sistema: los dos portales.

**Stack:** Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4 · Vite 7 · Carbon 3.11. Sistema de diseño «editorial técnico» en `.claude/skills/nodico-design/SKILL.md` y `tailwind.config.js`.

### Lo que ya existe

- Modelos `User`, `Plane`, `Suscripcion`, `Espacio`, `Reserva`, `Checkin`, `Factura`, `Comunicado`.
- Portal de miembro en `/portal` con `Dashboard`, `MiSuscripcion`, `MisReservas`, `MisFacturas`, `Reservar`.
- Portal operativo en `/dashboard` con planes, espacios, miembros, reservas, check-ins, facturas, anuncios, eventos y reportes.
- Un motor de reservas en `Portal/ReservasController` con control de cupos… **que hoy no funciona.** Ver Fase 0.

### Las cuatro membresías y lo que incluyen

| Plan | Precio | Coworking | Salas privadas y juntas | Sala de contenido | Asesor IYEM |
|---|---|---|---|---|---|
| Day-Pass | $79 / 1 día | 1 día | — | 1 hora | — |
| Nódico Flex | $249 / 4 días | 4 días | — | 4 h (1 por día) | — |
| **Nodo Pro** | $599 / mes | Ilimitado | **10 h/mes, máx. 2 h/día** | **10 h/mes** | **4 h/mes, 1 h/día** |
| Nodo Match (2 pax) | $799 / mes | Ilimitado | 20 h/mes, máx. 2 h/día | 15 h/mes | — |

### Espacios reservables

Oficinas privadas · salas de juntas · sala de creación de contenido · sala de fotografía · salones para eventos (Yucatán Emprende 1 y 2) · área de coworking.

### Decisiones ya tomadas por Nódico — no las replantees

| Tema | Decisión |
|---|---|
| Ciclo de horas | **Aniversario de la suscripción.** Quien contrata el 15 de marzo tiene sus horas del 15 de marzo al 14 de abril. Sin acumulación: lo no usado se pierde. |
| Cancelaciones | **Devuelve horas si cancela con 2 horas o más de anticipación.** Cancelación tardía y no-show consumen las horas igual. |
| Asesor IYEM | **Solicitud que el operativo confirma.** El miembro pide día y horario preferido; recepción asigna asesor y confirma. Sin agendas de asesores por ahora. |
| Facturación | **Solo recopilar y resguardar datos fiscales.** No se timbra CFDI desde el sistema; los datos se entregan a contabilidad. |

**Antes de empezar:** rama `feature/portales`, lee la skill de diseño y `docs/AUDITORIA-NODICO.md`, revisa qué skills tienes disponibles y usa las que apliquen. Al terminar, corre `/security-review`.

---

# FASE 0 — El motor de reservas está roto

Arregla esto **antes** de construir nada encima. Hoy el control de horas no sirve, y todo lo que se apoye en él heredará el error.

## BUG-01 · Crítico · La contabilidad de horas está invertida

En `Portal/ReservasController` se calcula la duración así:

```php
$horas = $horaFin->diffInMinutes($horaInicio) / 60;
```

**En Carbon 3 —y el proyecto usa 3.11.4— `diffInMinutes` devuelve un valor con signo, medido desde el objeto hacia el argumento.** Es decir, `$horaFin->diffInMinutes($horaInicio)` da la diferencia de fin a inicio: **un número negativo**. En Carbon 2 devolvía el valor absoluto, y de ahí viene el error.

Consecuencias, todas activas hoy:

- `$horas` es negativo en todo el flujo de creación.
- Las comprobaciones de cupo (`horasSalaRestantes() < $horas`) pasan siempre, porque cualquier saldo es mayor que un número negativo. **Un miembro puede reservar sin límite.**
- `increment('horas_sala_usadas', $horas)` con un negativo **resta** consumo en vez de sumarlo. Reservar hace que sobren más horas.
- Al cancelar, `decrement(..., max(0, $horas))` con `$horas` negativo evalúa `max(0, negativo)` = 0: no devuelve nada.
- La suma del límite diario acumula negativos, así que el tope de 2 horas por día nunca se alcanza.

Corrígelo en todos los puntos donde se calcule una duración, y **escribe una prueba que falle con el código actual** para que no vuelva.

## BUG-02 · Alto · Dos fuentes de verdad para el mismo permiso

`ReservasController::create` decide qué espacios mostrar con `$plan->incluye_sala_juntas`, pero `store` valida el cupo con `$plan->horas_sala_mes`. Son dos campos distintos gobernando la misma regla, y pueden contradecirse.

Además, `horasSalaRestantes()` nunca devuelve `null`, pero el controlador comprueba `!== null` antes de usarla: esa guarda es código muerto que da falsa sensación de que el caso «sin límite» está contemplado.

Unifica: **la bolsa de horas es la única fuente de verdad.** `null` significa una cosa y solo una en cada campo, documentada en el modelo. Retira `incluye_sala_juntas` o conviértelo en un accesor derivado.

## BUG-03 · Alto · Carrera en la doble reserva

La comprobación de traslape ocurre **fuera** de la transacción, antes de abrirla. Dos personas reservando la misma sala al mismo tiempo pasan ambas la comprobación y ambas escriben.

Mete la comprobación dentro de la transacción con bloqueo pesimista sobre las filas del espacio y la fecha, **y** añade una restricción en base de datos que impida el traslape. La validación en aplicación sola nunca es suficiente para esto.

## BUG-04 · Alto · Una reserva se puede cancelar dos veces

`destroy()` no comprueba el estatus antes de cancelar. Dos peticiones seguidas devuelven las horas dos veces. Verifica que esté `Confirmada` antes de tocar nada.

## BUG-05 · Medio · `dias_usados` nunca se incrementa

El campo existe en `suscripciones`, `diasRestantesMes()` lo lee, y **nada en el sistema lo actualiza**. El control de días de Day-Pass (1 día) y Nódico Flex (4 días) no funciona: esos miembros tienen acceso indefinido. Debe incrementarse en el check-in, una vez por día natural, no por reserva.

## BUG-06 · Medio · Las etiquetas de tipo de espacio no cubren los tipos reales

`Espacio::getTipoLabelAttribute()` conoce `escritorio`, `oficina_privada`, `sala_juntas`, `cabina_telefonica`, `lounge` y `salon_eventos`, pero el sistema opera con `privado`, `contenido`, `fotografia` y `coworking`. Esos cuatro se muestran al usuario como texto crudo en minúsculas.

Hay además tipos duplicados: `privado` y `oficina_privada` significan lo mismo. Consolida el catálogo en un enum de PHP, migra los datos existentes y borra los tipos muertos.

## BUG-07 · Menor · `return back()` dentro de `DB::transaction()`

Devolver una respuesta desde el cierre de la transacción no la revierte: sin excepción, la transacción confirma igual. Hoy es inofensivo porque las salidas tempranas ocurren antes de escribir, pero es un patrón que muerde en cuanto alguien añada una escritura arriba. Lanza excepciones de validación en vez de devolver respuestas desde dentro.

---

# FASE 1 — Lo que falta en el modelo de datos

## 1.1 · Libro de movimientos de horas — cámbialo por esto

Hoy el consumo vive en tres contadores sueltos (`horas_sala_usadas`, `horas_contenido_usadas`, `dias_usados`). Un contador no se puede auditar, no se puede corregir con constancia y no explica de dónde salió el saldo. El BUG-01 llevaba meses invisible justo por eso.

**Crea una tabla de movimientos de horas:** suscripción, tipo de bolsa (sala, contenido, asesoría, días), cantidad con signo, motivo (`reserva`, `cancelación`, `ajuste manual`, `no-show`, `reinicio de ciclo`), referencia a la reserva, usuario que lo originó y fecha.

El saldo se calcula sumando movimientos. Mantén los contadores actuales como caché con un comando que los reconstruya desde el libro. Esto te da:

- auditoría real: cada hora consumida tiene su porqué;
- corrección segura: recepción repone horas con un movimiento y una nota, sin editar contadores a mano;
- una prueba de consistencia que compare caché contra suma y avise si divergen.

## 1.2 · Horas de asesoría IYEM

No existen en el modelo. Añade a `planes` el cupo mensual y el tope diario de asesoría, y a `suscripciones` su bolsa correspondiente. Crea una tabla de **solicitudes de asesoría**: miembro, tema, día y horario preferidos, estado (`solicitada`, `confirmada`, `realizada`, `cancelada`), asesor asignado, notas, y fecha real.

Las horas se descuentan al **confirmar**, no al solicitar. Si el operativo rechaza o el miembro cancela a tiempo, no se descuenta nada.

## 1.3 · Datos fiscales

Crea una tabla propia ligada al usuario, con: RFC, razón social, régimen fiscal, uso de CFDI, código postal fiscal y correo para facturas. Nada de esto va en `users`.

- Valida el **formato** del RFC (12 posiciones para persona moral, 13 para física) y normalízalo a mayúsculas sin espacios. No pretendas validar contra el SAT.
- Régimen fiscal y uso de CFDI salen de catálogos fijos del SAT, no de campos de texto libre. Guárdalos como listas en configuración.
- La sección debe explicar con claridad que **la factura la emite contabilidad del IYEM**, no el sistema, y en cuánto tiempo llega.
- Trátalo como dato personal sensible: acceso restringido, y toda consulta o cambio queda en la bitácora.

## 1.4 · Horarios de operación y reglas de reserva

Nada de esto está modelado y todo hace falta para que las reservas tengan sentido:

- **Horario del espacio**: lunes a viernes de 9:00 a 19:00 según `config/nodico.php`. Ninguna reserva puede caer fuera, ni cruzar el cierre.
- **Granularidad y duración**: bloques de 30 minutos, mínimo 1 hora.
- **Antelación**: mínima (no se reserva para dentro de 10 minutos) y máxima (no se reserva con tres meses de anticipación).
- **Bloqueos**: el operativo debe poder marcar un espacio como no disponible por mantenimiento o evento privado, y esos bloqueos compiten con las reservas igual que cualquier otra.
- **Días festivos** y cierres especiales.

## 1.5 · Ciclo de aniversario

Un comando programado que cada día detecte las suscripciones que cumplen ciclo y registre el movimiento de reinicio de bolsas. Debe ser **idempotente**: correrlo dos veces el mismo día no puede reiniciar dos veces. Y debe dejar rastro, para que si el servidor estuvo caído un día, se pueda recuperar sin duplicar.

## 1.6 · No-show

Si nadie hace check-in de una reserva y pasa su horario, márcala automáticamente como `No_Show` y **no** devuelvas las horas. El estatus ya existe en el modelo pero nada lo asigna. Es lo que hace que la regla de las 2 horas signifique algo.

---

# FASE 2 — Portal del miembro

Ruta `/portal`. Diseño sobre el sistema de Nódico: Carmen Sans en titulares, GT Eesti en cuerpo, amarillo de marca como acento, fondos `cream` y `tinta`. Cálido y claro, no un panel de control frío: el usuario es un emprendedor de 25 años, no un administrador de sistemas.

**Regla de oro de esta vista:** en todo momento la persona debe poder responder sin pensar «¿cuánto me queda y hasta cuándo?».

### 2.1 · Inicio

- **Estado de la membresía arriba de todo:** plan, vigencia y días restantes. Si está pendiente de activación o suspendida, dilo con claridad y con la acción para resolverlo.
- **Medidores de bolsas**, uno por cada una que aplique al plan: salas privadas y juntas, sala de contenido, asesoría IYEM, días de coworking. Cada uno con horas usadas, restantes y fecha de reinicio. Visual, no una tabla: barras o anillos que se lean de un vistazo.
- **Próxima reserva** destacada, con su espacio, horario y cuenta regresiva.
- Accesos rápidos a reservar y a solicitar asesoría.
- Avisos de Nódico (`Comunicado`).
- Si el plan **no** incluye una bolsa, no muestres el medidor en cero: explica qué plan la incluye y enlaza a mejorar la membresía. Un cero se lee como error; una invitación se lee como oferta.

### 2.2 · Mi perfil

Datos personales, foto, teléfono, empresa y ocupación. Añade **contacto de emergencia**, que en un espacio físico es información necesaria. Preferencias de notificación. Y un bloque de seguridad que enlace a lo construido en el trabajo de autenticación: contraseña, 2FA y sesiones activas.

### 2.3 · Datos fiscales

Formulario con lo descrito en 1.3. Estado visible: «completos» o «faltan datos», porque el miembro necesita saber si puede pedir factura. Explica el proceso real y a dónde escribir.

### 2.4 · Mi membresía

Plan actual con todo lo que incluye, desglosado. Vigencia y renovación. Historial de membresías anteriores. Botón para renovar o cambiar de plan que lleve al enlace de Stripe correspondiente. En **Nodo Match**, la gestión del acompañante: invitarlo, ver su estado de Face ID, quitarlo.

### 2.5 · Reservar

El corazón del portal. Que sea un placer usarlo, no un formulario.

- **Primero el tipo de espacio**, con tarjetas que digan qué bolsa consume cada uno y cuánto queda de ella.
- **Después el día**, en un calendario que ya muestre disponibilidad — no que la descubra al enviar.
- **Después el horario**, sobre una franja visual del día con los bloques ocupados marcados. Elegir arrastrando o tocando, no escribiendo horas a mano.
- **Validación en vivo, antes de enviar:** si va a exceder el tope diario de 2 horas, si no le alcanza la bolsa, si cae fuera de horario, dilo en el momento y con el número exacto que falta.
- **Resumen antes de confirmar:** espacio, fecha, horario, cuánto consume y **cuánto le quedará después**.
- Confirmación con opción de añadir al calendario y aviso claro de la regla de cancelación.
- En iPhone tiene que funcionar bien: es donde más se va a usar.

### 2.6 · Mis reservas

Próximas y pasadas, separadas. Cada una con su estado. En las próximas, el botón de cancelar debe mostrar **si devuelve horas o no** antes de pulsarlo, con el tiempo que falta para el límite de 2 horas. Nadie debe descubrir la penalización después de aceptarla.

### 2.7 · Asesoría IYEM

Solo si el plan la incluye. Solicitud con tema, día y horario preferidos. Estado de cada solicitud, y aviso cuando el operativo confirme. Historial.

### 2.8 · Mis accesos y mis pagos

Historial de entradas y salidas al espacio, y días consumidos si el plan es por días. Historial de pagos con concepto, fecha y estado, y desde dónde solicitar factura.

---

# FASE 3 — Portal operativo

Ruta `/dashboard`. Aquí el criterio cambia: **esto es una herramienta, no un documento.** Se opera a diario, con prisa, muchas veces desde el mostrador con alguien esperando enfrente. Densidad de información, estado legible de un vistazo, y cero clics de adorno.

Mantén la identidad de Nódico, pero prioriza: lo urgente antes que lo bonito, el estado codificado en forma además de en número, y color semántico —bien, atención, problema— separado del amarillo de marca.

Respeta los roles definidos en el trabajo de autenticación: **admin** ve todo; **staff** (recepción) opera el día a día pero no toca precios, planes, reportes financieros ni configuración.

### 3.1 · Tablero del día

Lo primero que ve recepción al abrir:

- Quién está dentro ahora mismo, con hora de entrada.
- Reservas de hoy en una línea de tiempo por espacio: se ve de golpe qué sala está ocupada, cuál se libera pronto y cuál está vacía.
- Alertas que exigen acción: reservas por empezar sin check-in, membresías que vencen esta semana, solicitudes de asesoría sin responder, miembros sin Face ID.
- Buscador global de miembro siempre a la mano, que responda por nombre, correo o teléfono.

### 3.2 · Miembros

Buscador con filtros por plan, estado y vencimiento. Ficha completa de cada miembro: datos, membresía, bolsas con su consumo, historial de reservas y accesos, Face ID, acompañante, notas internas y datos fiscales.

**Ajuste manual de horas:** recepción debe poder reponer o descontar horas, siempre con motivo obligatorio, y siempre registrándose como movimiento en el libro de la Fase 1.1. Nunca editando un contador directo.

### 3.3 · Membresías

Alta manual (para quien pague en efectivo o transferencia), activar, suspender, renovar y cambiar de plan. Al cambiar de plan, decidir y documentar qué pasa con las bolsas en curso. Vista de las que vencen pronto para poder llamar antes.

### 3.4 · Agenda de espacios

Calendario semanal con una columna por espacio, tipo línea de tiempo. Crear, mover y cancelar reservas arrastrando. Bloqueos por mantenimiento. Ver de quién es cada reserva y saltar a su ficha.

El operativo puede reservar **por encima** del cupo de un miembro —a veces hay que resolver— pero el sistema debe pedir confirmación explícita y dejar constancia de quién autorizó el sobrecupo.

### 3.5 · Salones para eventos

Los salones Yucatán Emprende no funcionan como las salas: se cotizan, se rentan por hora a $600, se les añade coffee break ($45 por persona hasta 25 pax, $35 desde 100 pax) y los contrata gente que no es miembro.

Necesita: cotizador con cálculo automático, reserva de salón con datos del cliente externo, tipo de montaje (herradura, escuela, mesas de trabajo, auditorio) con la capacidad correspondiente, y estado del anticipo. Conecta con los prospectos que llegan por el formulario «Hablemos» del sitio público.

### 3.6 · Espacios

Alta y edición de oficinas privadas, salas de juntas, sala de contenido, sala de fotografía, área de coworking y salones. Capacidad, precio por hora, horario propio si difiere del general, amenidades, fotos y disponibilidad.

### 3.7 · Solicitudes de asesoría

Bandeja de pendientes. Confirmar asignando asesor y horario, o rechazar con motivo. Al confirmar se descuentan las horas. Historial y carga por asesor.

### 3.8 · Check-in y check-out

Optimizado para velocidad de mostrador: buscar, un clic para entrada, un clic para salida. Debe incrementar `dias_usados` una sola vez por día natural (BUG-05) y enlazar con la reserva correspondiente si existe.

### 3.9 · Facturación

Bandeja de solicitudes de factura con los datos fiscales del miembro ya completos y validados, listos para pasar a contabilidad. Exportación a CSV o Excel. Estado de cada solicitud, para que el miembro pueda ver en su portal si ya se emitió.

### 3.10 · Reportes

Solo para admin. Ocupación por espacio y por franja horaria — qué salas se saturan y cuáles están muertas. Horas consumidas contra incluidas por plan, que es lo que dice si los cupos están bien calibrados. Ingresos por plan y por renta de salones. Tasa de no-show. Miembros en riesgo: los que dejaron de venir. Todo con rango de fechas y exportable.

### 3.11 · Bitácora

Registro de toda acción sensible: ajustes de horas, sobrecupos, cambios de plan, suspensiones, consultas a datos fiscales. Quién, cuándo, qué y por qué. Filtrable.

---

# FASE 4 — Pruebas

Las reglas de negocio son el producto: si no están probadas, no existen. Como mínimo:

- Prueba de regresión de BUG-01: una reserva de 2 horas consume exactamente 2, no −2.
- El tope de 2 horas por día de Nodo Pro se respeta sumando **varias** reservas del mismo día.
- No se puede reservar sin bolsa suficiente, ni fuera del horario de operación, ni fuera de la vigencia de la membresía.
- Dos reservas simultáneas sobre la misma sala y horario: solo una gana (prueba de concurrencia).
- Cancelar con más de 2 horas devuelve; con menos, no. Cancelar dos veces no devuelve dos veces.
- El no-show automático marca y no devuelve.
- El reinicio de aniversario es idempotente: correrlo dos veces no duplica.
- El saldo calculado desde el libro de movimientos coincide siempre con el contador en caché.
- Un miembro no puede ver ni tocar reservas, datos fiscales ni fichas de otro miembro.
- Un usuario `staff` no accede a reportes ni a edición de planes.
- El RFC se valida en formato y se guarda normalizado.

Además: `npm run build` y `php artisan test` sin errores, consola limpia, recorrido con teclado, y prueba en 375 / 393 / 430 / 744 / 1024 / 1366 px. El portal operativo se usa mucho en tablet en el mostrador: cuídalo especialmente ahí.

---

# FASE 5 — Despliegue

Sigue `docs/DEPLOY.md`. Hostinger no tiene Node —los assets se compilan en local— y hay que sincronizar `public_html/build/` y `public_html/public/build/`.

Dos cosas propias de este trabajo:

- **Migración de datos existentes.** Si ya hay suscripciones con contadores, genera los movimientos iniciales del libro a partir de ellos y verifica que los saldos cuadren antes y después. Hazlo en un comando reversible, no en una migración a ciegas.
- **Tareas programadas.** El reinicio de aniversario y el marcado de no-show necesitan un cron en Hostinger. Documenta la línea exacta en `docs/DEPLOY.md` y **verifica que corre de verdad en el servidor**: una tarea programada que nadie comprueba es una tarea que no existe.

---

## FORMA DE TRABAJAR

- Muéstrame el plan antes de empezar y espera mi visto bueno.
- Orden: Fase 0 → Fase 1 → Fase 2 completa → **me la enseñas** → Fase 3 → pruebas → despliegue.
- La Fase 0 va sola en su propio commit, con sus pruebas: es una corrección de datos, no una funcionalidad.
- Enséñame cada pantalla conforme la termines; el resto avánzalo sin consultarme cada detalle.
- Si una regla de negocio no está definida en este documento, **no la inventes**: anótala y pregúntame. Las reglas mal supuestas cuestan dinero real.
- Todo en español, con acentos correctos.
