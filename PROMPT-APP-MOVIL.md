# Prompt para Claude Code — App móvil de Nódico en React Native + Expo

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

Nódico es el coworking del Instituto Yucateco de Emprendedores. Existe el sistema completo en **Laravel 12 + Inertia 2 + Vue 3**: sitio público, portal del miembro, panel operativo, cobros con Stripe, facturación y control de acceso con reconocimiento facial.

**Lo que se quiere:** una **aplicación móvil nativa en React Native con Expo**, que se pueda ver desde el primer día en **Expo Go**, y que se vea preciosa — no un sitio web metido en una ventana.

**Rama:** `feature/app-movil`.

---

## LO PRIMERO, PORQUE DEFINE EL TAMAÑO DEL TRABAJO

**Hoy no existe ninguna API.** Inertia es *server-driven*: Laravel devuelve páginas con sus datos ya dentro, no JSON. Una app en React Native no puede consumir eso.

Así que este proyecto son **dos cosas**, no una:

1. **Construir una API en Laravel** que exponga lo que la app necesita.
2. **Construir la app** que la consume.

La parte uno es menos vistosa pero es la que sostiene todo. No la saltes ni la improvises sobre la marcha: si la API queda mal definida, la app se vuelve un parche sobre otro.

**El sitio público se queda en la web.** Nadie descarga una app para leer «quiénes somos» — eso se descubre en el buscador y en Instagram. La app es **para miembros**: lo que alguien abre tres veces por semana desde su teléfono. Lo único público que vale la pena dentro es una vista previa de los planes para quien todavía no tiene cuenta.

---

# FASE 0 — La API en Laravel

Antes de tocar la app.

- **Autenticación con Laravel Sanctum en modo token.** La web usa cookies de sesión; el móvil necesita tokens. Son dos caminos que conviven sin estorbarse.
- Rutas bajo `/api/v1/`, versionadas desde el principio. El día que la app cambie, las versiones viejas siguen instaladas en los teléfonos de la gente durante meses.
- **Recursos de Laravel** para dar forma a las respuestas. Nunca devuelvas un modelo entero: expón solo lo que la app pinta, y nada de datos de otras personas.
- **La autorización se repite en la API.** Los Gates que protegen el panel no aplican solos aquí. Un miembro no puede leer ni las reservas, ni las horas, ni los pagos de otro — pruébalo contra el JSON, no contra la pantalla.
- Límite de peticiones por token.
- **Revocación**: cerrar sesión desde la app invalida ese token, y desde «Mi seguridad» en la web se puede cerrar la sesión de un dispositivo perdido.

**Lo que la API tiene que exponer:** perfil, bolsas de horas con su ciclo, espacios y su disponibilidad, crear y cancelar reservas, solicitudes de asesoría, membresía vigente, pagos y facturas, avisos, y la credencial de acceso.

**Entregable:** `docs/API-MOVIL.md` con cada ruta, su forma de respuesta y qué permiso exige. Enséñamelo antes de escribir la app.

---

# FASE 1 — El proyecto Expo y el sistema de diseño

Proyecto nuevo en `app-movil/` dentro del repositorio, con la versión estable más reciente de Expo SDK y **TypeScript**. Comprueba cuál es la vigente antes de fijarla.

**Navegación:** Expo Router, con pestañas abajo. Cinco destinos como máximo: Inicio, Reservar, Credencial, Mi membresía, Perfil.

## El sistema visual

La marca de Nódico traducida a móvil, no copiada de la web:

- **Fondo oscuro** (`#1A1918`) como base de la app. En un teléfono se ve premium, cansa menos de noche y hace que el amarillo de marca resalte de verdad.
- **Amarillo `#FFE124`** como único acento fuerte. Los colores de plan —coral, lima, morado— para distinguir membresías y estados.
- **Tipografía de marca**: Carmen Sans para números y títulos, GT Eesti para texto. Cárgalas con `expo-font`; los archivos están en `public/fonts/`. Los números grandes son el alma de esta app: cuántas horas quedan, cuántos días faltan.
- **Espaciado generoso.** El error más común al pasar una web a móvil es apretarlo todo.

## Lo que la hace sentirse nativa

Esto es lo que separa una app bonita de una web disfrazada:

- **Anillos de progreso animados** para las bolsas de horas, dibujados con SVG y animados con Reanimated. Es la pantalla principal y tiene que provocar abrirla.
- **Hojas deslizantes** para los detalles, no pantallas nuevas para todo.
- **Respuesta háptica** al confirmar una reserva o al cancelar. Cuesta una línea y cambia por completo la sensación.
- **Esqueletos de carga**, nunca un círculo girando.
- **Deslizar para actualizar** en las listas.
- **Transiciones nativas** entre pantallas, y gesto de regresar deslizando.
- **Estados vacíos con personalidad**: «Todavía no tienes reservas» con una ilustración y un botón, no una pantalla en blanco.
- Soporte de **modo claro y oscuro**, respetando el del sistema.

---

# FASE 2 — Acceso

- Correo y contraseña, **ingreso con Google**, y **enlace mágico** — los tres ya existen en el servidor.
- **Sesión persistente y segura**: el token va en el almacén seguro del sistema (`expo-secure-store`), nunca en almacenamiento normal.
- **Bloqueo con biometría** para volver a abrir la app: Face ID o huella. Es distinto del reconocimiento facial de la entrada; esto solo protege la app en el teléfono.
- Cuenta suspendida, correo sin verificar o consentimiento pendiente: cada caso con su pantalla explicada, no un error genérico.

---

# FASE 3 — Las pantallas

**Inicio.** Lo primero que se ve: los anillos de horas —salas, contenido, asesoría— con lo consumido y lo disponible, la fecha de reinicio del ciclo, la próxima reserva con cuenta regresiva y accesos rápidos. Si la membresía está por vencer o suspendida, se dice aquí y con claridad.

**Reservar.** El flujo que más se va a usar y donde más se nota si la app es buena. Elegir espacio, después día en un calendario horizontal deslizable, después horario sobre una franja del día con los huecos libres marcados. Validación en vivo antes de confirmar: si excede su tope diario o no le alcanza la bolsa, se dice en el momento y con el número exacto. Resumen antes de confirmar, con cuánto le quedará después.

**Credencial.** Su código QR a pantalla completa, con el brillo subido automáticamente. **Tiene que funcionar sin conexión**: quien llega sin datos sigue pudiendo identificarse. Guárdala localmente y actualízala cuando haya red.

**Mis reservas.** Próximas y pasadas. Al deslizar una próxima aparece cancelar, y antes de confirmar dice **si devuelve las horas o no**, según la regla de las dos horas.

**Mi membresía.** Plan, vigencia, qué incluye, y el acompañante si es Nodo Match.

**Asesoría IYEM.** Temas disponibles, solicitar, y el estado de lo solicitado.

**Pagos y facturas.** Historial y descarga.

**Perfil.** Datos, foto, datos fiscales, seguridad y cerrar sesión.

---

# FASE 4 — Lo nativo

- **Notificaciones push**: recordatorio de reserva, membresía por vencer, asesoría confirmada, factura lista. Pide el permiso **cuando tenga sentido** —después de la primera reserva, por ejemplo— nunca al abrir por primera vez.
- **Cámara** para la foto de perfil.
- **Añadir la reserva al calendario** del teléfono.
- **Compartir** la credencial o una reserva.
- **Sin conexión**: lo último que se cargó se conserva y se muestra con un aviso honesto de que puede estar desactualizado. Una app que se pone en blanco sin señal se siente rota.

---

# FASE 5 — Expo Go y sus límites

Vas a poder ver la app en Expo Go desde el primer día, que es justo lo que se pidió. Pero **Expo Go no puede con todo**, y conviene saberlo antes de chocar:

- **Notificaciones push remotas en iOS no funcionan en Expo Go.** Las locales sí. Para probar push de verdad hace falta una compilación de desarrollo.
- **Sign in with Apple** tampoco funciona en Expo Go.
- El **SDK de Stripe** necesita compilación de desarrollo.

**Cómo trabajarlo:** construye toda la interfaz y la navegación para Expo Go, que es donde el ciclo de prueba es más rápido. Cuando toque lo nativo, pasa a una **compilación de desarrollo con EAS**, que se instala igual de fácil y ya no tiene esos límites.

Deja documentado en `docs/APP-MOVIL.md` qué se prueba en Expo Go y qué exige compilación propia. **Verifica estos límites contra la documentación vigente de Expo antes de darlos por ciertos** — cambian entre versiones.

---

# FASE 6 — Pruebas

- Un miembro no puede leer datos de otro **por la API**, no solo en la pantalla.
- El token se guarda en el almacén seguro y se invalida al cerrar sesión.
- La credencial se ve sin conexión.
- Las reglas de reserva —tope diario, bolsa insuficiente, fuera de horario— se aplican **en el servidor**, aunque la app las anticipe.
- Cancelar dice correctamente si devuelve horas.
- Nada queda tapado por el notch ni por la barra de gestos.
- Pruébala en iPhone y en Android de verdad, no solo en simulador.

---

# FASE 7 — Antes de publicar

Cuando llegue el momento de las tiendas, hay dos cosas que conviene saber ya:

- **Sign in with Apple será obligatorio**, porque la app ofrece ingreso con Google. Y hoy está bloqueado: la librería necesita `ext-sodium`, que Hostinger no tiene habilitada (ver `docs/AUTH-PROVEEDORES.md`). Es la ruta más larga del proyecto — **abre el ticket al hosting desde ahora**.
- **Los pagos pueden seguir con Stripe.** Apple exige su sistema de compras para bienes digitales, pero las membresías dan acceso a un espacio físico y eso está exento. Confírmalo con las directrices vigentes y déjalo escrito.

Cuentas: Apple Developer 99 USD al año, Google Play 25 USD una vez. **A nombre del instituto, no de una persona.**

---

## FORMA DE TRABAJAR

- **La Fase 0 primero y sola.** Enséñame `docs/API-MOVIL.md` y espera visto bueno antes de crear el proyecto de Expo.
- Después: proyecto y sistema visual → acceso → Inicio → **me lo enseñas en Expo Go** → el resto de las pantallas.
- No toques la app de asistencia que ya existe en Expo. Primero esta; después se decide si aquella se absorbe.
- **No dupliques reglas de negocio en la app.** La app puede anticipar y avisar, pero quien decide es el servidor. Dos motores de reglas siempre terminan discrepando.
- Todo en español, con acentos correctos.
