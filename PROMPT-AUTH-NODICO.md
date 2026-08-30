# Prompt para Claude Code — Sistema de autenticación de Nódico

> Pega todo lo que está debajo de la línea en Claude Code, dentro de `C:\xampp\htdocs\coworkhub`.

---

## CONTEXTO

El sitio público de Nódico ya está construido (Laravel 12 · Inertia 2 · Vue 3 `<script setup lang="ts">` · Tailwind 3.4 · Vite 7) con un sistema de diseño «editorial técnico» documentado en `.claude/skills/nodico-design/SKILL.md` y en `tailwind.config.js`. Toca ahora rehacer por completo la autenticación.

**Público:** emprendedores de 18 a 30 años en Mérida. El acceso tiene que sentirse rápido y moderno, no un trámite.

**Decisiones ya tomadas por Nódico — no las replantees:**

| Tema | Decisión |
|---|---|
| Registro | Abierto a cualquiera, con verificación de correo obligatoria. La membresía nace inactiva hasta que se compra un plan o recepción la activa. |
| Google | Activo desde el día uno. |
| Apple | **No hay cuenta de Apple Developer todavía.** Se implementa completo pero apagado por configuración, listo para encenderse. |
| 2FA | Opcional para todos, tanto operativo como miembros. |

### Estado actual del código

- **Laravel Breeze** con Inertia ya instalado: rutas en `routes/auth.php`, controladores en `app/Http/Controllers/Auth/`, páginas en `resources/js/Pages/Auth/`.
- `User` tiene el campo `tipo` con dos valores: `admin` y `miembro`.
- Middlewares `EsAdmin` (alias `admin`) y `EsMiembro` (alias `miembro`) en `bootstrap/app.php`.
- Portal operativo en `/dashboard` (middleware `auth`, `verified`, `admin`). Portal de miembro en `/portal` (middleware `auth`, `miembro`).
- **No** hay Socialite instalado.

### ⚠️ Dos conflictos con el trabajo en curso — resuélvelos primero

1. **Las páginas de Auth actuales dependen de CSS que está marcado para borrarse.** `Pages/Auth/Login.vue` usa `useCursor()`, `.cursor-zone`, `.noise` y `.reveal-scale`, todo eso incluido en el hallazgo **FE-15** de la auditoría, que manda eliminarlo de `app.css`. Además apunta a `/logo-nodico-blanco.png`, que el hallazgo **IMG-04** manda borrar por ser un duplicado huérfano. **Rehaz las pantallas de Auth sobre el sistema de diseño nuevo**, sin depender de nada de eso.
2. Si la rama de la auditoría (`feature/auditoria-y-portada`) todavía no está integrada, coordina: este trabajo debe partir de ella, no competir con ella.

**Antes de empezar:** rama `feature/auth`, lee `.claude/skills/nodico-design/SKILL.md`, revisa qué skills tienes disponibles y usa las que apliquen. Al terminar, corre `/security-review` sobre el diff completo.

---

# FASE A — Identidad, roles y los bugs que hay que arreglar

## A.1 · Bucle infinito de redirección entre los dos middlewares

`EsAdmin` redirige a `portal.dashboard` cuando el usuario no es admin. `EsMiembro` redirige a `dashboard` cuando el usuario no es miembro. Un usuario cuyo `tipo` no sea ninguno de los dos —`null`, un valor nuevo, un registro creado a mano— entra en un rebote infinito entre `/dashboard` y `/portal` hasta que el navegador corta.

Rehaz los dos middlewares para que un rol desconocido termine en una página de error clara (403 con explicación y enlace a contacto), nunca en otra redirección. Ninguna ruta de redirección puede depender de que el rol sea exactamente uno de dos valores.

## A.2 · Los miembros nunca necesitan verificar su correo

`/dashboard` lleva el middleware `verified`, pero `/portal` **no**. Y `RegisteredUserController` hace `Auth::login($user)` y manda directo al portal. Resultado: hoy nadie verifica nada, con lo que cualquiera puede registrarse con un correo ajeno.

Con la decisión de «registro abierto con verificación obligatoria», esto cambia:

- Añade `verified` a todo el grupo `/portal`.
- Tras registrarse, la persona entra pero cae en la pantalla de «verifica tu correo», sin acceso al resto del portal.
- Permite reenviar el correo, con límite de envíos.
- Los enlaces de verificación caducan (60 minutos) y son de un solo uso.
- Quien entre con Google se considera verificado automáticamente: Google ya validó ese correo.

## A.3 · Roles: añade `staff`

Hoy solo hay `admin` y `miembro`, pero el portal operativo lo va a usar también recepción, que no debería poder tocar planes, precios ni reportes. Añade un tercer rol `staff` con permisos de operación diaria (check-ins, reservas, ver miembros) y sin acceso a configuración ni facturación.

Usa un enum de PHP para los roles en vez de cadenas sueltas, y migra los valores existentes. Define los permisos con Gates o Policies de Laravel, **no** con `if ($user->tipo === 'admin')` regado por los controladores.

## A.4 · `tipo` está en `$fillable`

Hoy no es explotable —`RegisteredUserController` fija el valor a mano— pero es una bomba con temporizador: el día que alguien escriba `User::create($request->all())` o un `update()` masivo, el rol se vuelve asignable desde el formulario. Sácalo de `$fillable` y cámbialo solo por método explícito.

## A.5 · Estado de la cuenta, separado del rol

La membresía nace inactiva. Añade un campo de estado (`pendiente`, `activa`, `suspendida`) independiente del rol, y que el portal muestre con claridad qué puede hacer una cuenta pendiente: ver su perfil y comprar un plan, sí; reservar salas, no.

---

# FASE B — Seguridad del núcleo

Esto es lo que hace que el sistema sea «súper seguro» de verdad, más allá de la pantalla bonita.

### Contraseñas

- Reglas: mínimo 10 caracteres, mezcla de letras y números, **y `Password::uncompromised()`**, que consulta la base de contraseñas filtradas de Have I Been Pwned por k-anonimato — nunca se envía la contraseña, solo cinco caracteres del hash. Es la medida individual con mejor relación esfuerzo/beneficio que existe.
- **No** exijas símbolos ni cambio periódico: el NIST desaconseja ambos desde 2017 porque empujan a la gente a contraseñas peores.
- Medidor de fuerza en vivo en el formulario, que evalúe de verdad —no que cuente caracteres— y explique qué falta.
- `Hash::needsRehash()` al iniciar sesión, para migrar hashes viejos de forma transparente.

### Límite de intentos

- El límite actual son 5 intentos por combinación de correo e IP. **Todos los miembros conectados al wifi de Nódico salen por la misma IP**: cinco intentos fallidos de una persona en el espacio no deben bloquear a las demás. Separa los contadores: uno estricto por cuenta y otro más holgado por IP.
- Retraso creciente en vez de bloqueo seco, y mensaje que diga cuánto falta.
- Registra los bloqueos y avisa por correo al titular de la cuenta cuando se alcance el límite.

### Enumeración de cuentas

Que ninguna respuesta permita averiguar si un correo está registrado: mismo mensaje y **mismo tiempo de respuesta** en login fallido, en recuperación de contraseña y en registro con correo repetido. Para el tiempo, compara siempre contra un hash señuelo cuando el usuario no exista.

### Sesiones

- Regenerar el identificador de sesión en cada cambio de privilegio: al entrar, al verificar el correo, al activar 2FA.
- Cookies `secure`, `httponly` y `SameSite=Lax`.
- Caducidad por inactividad más corta en el portal operativo que en el de miembros.
- Pantalla de «sesiones activas» donde la persona vea sus dispositivos y pueda cerrar los demás. Cambiar la contraseña cierra todas las otras sesiones.
- `password.confirm` sobre las acciones sensibles: cambiar correo, cambiar contraseña, desactivar 2FA, borrar cuenta.

### Bitácora

Tabla de eventos de autenticación: ingreso correcto, ingreso fallido, bloqueo, cambio de contraseña, alta y baja de 2FA, vinculación de proveedor social, cierre de sesión remoto. Guarda fecha, IP, agente de usuario y resultado. Visible para el admin y, en su parte propia, para cada miembro.

### Cabeceras y transporte

- HSTS, `X-Content-Type-Options`, `Referrer-Policy` y `X-Frame-Options` en todas las respuestas.
- Una Content Security Policy que contemple los orígenes de Google, YouTube, Instagram, Luma y Maps que ya usa el sitio.
- Forzar HTTPS en staging y producción.

### Correos

Los mensajes de verificación, recuperación y alertas de seguridad deben ir con la identidad de Nódico, no con la plantilla gris de Laravel. Y ninguno debe incluir la contraseña ni un enlace que inicie sesión directamente.

---

# FASE C — Ingreso con proveedores externos

Instala `laravel/socialite`. Diseña la integración como **una tabla de identidades vinculadas** (`user_id`, `proveedor`, `id_del_proveedor`, `correo`, `avatar`, fechas), no como columnas sueltas en `users`: así una persona puede tener Google y contraseña a la vez, y añadir Apple después sin migrar nada.

## C.1 · Google — activo

- Botón «Continuar con Google» en Login y en Register, arriba del formulario, con separador «o con tu correo».
- Vinculación por correo verificado: si ya existe una cuenta con ese correo y contraseña, se vincula la identidad de Google en vez de crear una cuenta duplicada. **Solo** si Google reporta el correo como verificado.
- Quien entre con Google queda con el correo verificado y sin contraseña; ofrécele poner una desde su perfil, sin obligarlo.
- Nunca permitas desvincular el último método de acceso: dejaría a la persona fuera de su cuenta.

**Seguridad de OAuth, no negociable:**

- Parámetro `state` con valor aleatorio y verificación al volver. Es la única defensa contra CSRF en el flujo de OAuth y es el error más común.
- PKCE activado.
- URL de retorno declarada en una lista blanca; nunca tomes el destino de un parámetro de la petición.
- Valida `aud` e `iss` del token de Google.
- Cuenta con el caso de que la persona cancele a mitad y con el de que Google devuelva error: ambos deben terminar en la pantalla de login con un mensaje entendible, no en una excepción.

## C.2 · Apple — implementado pero apagado

Nódico todavía no tiene cuenta de Apple Developer. Deja el código completo y funcional, controlado por `NODICO_APPLE_LOGIN_ENABLED=false` en el `.env`: con la variable apagada, el botón no se renderiza y la ruta devuelve 404.

Escribe `docs/AUTH-PROVEEDORES.md` con los pasos exactos para encenderlo. Debe incluir estos puntos, que son donde todo el mundo tropieza:

- Requiere el **Apple Developer Program**, 99 USD al año. No hay nivel gratuito para Sign in with Apple en web.
- Hacen falta cuatro cosas distintas: un **App ID**, un **Services ID** (que es el `client_id` para web, no el App ID), una **llave privada `.p8`** de Sign in with Apple, y el **Team ID**.
- El `client_secret` de Apple **no es una cadena fija**: es un JWT firmado con la `.p8` que **caduca a los seis meses como máximo**. Hay que generarlo y renovarlo automáticamente con una tarea programada, o el ingreso deja de funcionar un día sin previo aviso. Usa `socialiteproviders/apple`, que ya resuelve esa parte.
- Apple exige HTTPS y **no acepta `localhost`**: las pruebas van contra `prueba.nodico.com.mx`.
- Los dominios y las URLs de retorno se registran y verifican en el portal de Apple.
- **Apple envía el nombre de la persona solo en el primer ingreso.** Si no lo guardas en ese momento, se pierde para siempre y la cuenta queda sin nombre.
- Apple ofrece «Ocultar mi correo»: llega una dirección de reenvío `@privaterelay.appleid.com`. Es un correo válido y hay que aceptarlo. Los mensajes a esa dirección funcionan, pero solo desde dominios registrados en Apple.
- Si algún día Nódico publica app móvil con otros ingresos sociales, las reglas de la App Store **obligan** a ofrecer también Sign in with Apple.

## C.3 · Qué otro proveedor conviene — evaluación

En el mismo documento, deja el análisis. Mi recomendación:

- **Enlace mágico por correo (sin contraseña).** El mejor siguiente paso: cero costo, cero dependencia de terceros y muy buena conversión en móvil, que es donde está el público. Enlace de un solo uso, caducidad de 15 minutos, atado al navegador que lo pidió. **Impleméntalo.**
- **Microsoft Entra ID.** El IYEM es una institución del gobierno de Yucatán y es muy probable que use Microsoft 365. Permitiría que admin y recepción entren con su cuenta institucional, lo cual además centraliza altas y bajas de personal en TI del instituto. **Vale la pena preguntarlo antes de descartarlo** — sería el ingreso ideal para el portal operativo.
- **LinkedIn.** Coherente con el público emprendedor, pero casi nadie lo usa como método de acceso. Bajo retorno para el trabajo que cuesta.
- **Facebook.** Mucha cobertura en México, pero la revisión de la app es lenta y arrastra un costo de percepción en privacidad. No lo recomiendo.
- **SMS con código.** Caro por mensaje y vulnerable a suplantación de SIM. No lo uses como método principal ni como segundo factor.

---

# FASE D — Segundo factor, opcional

Basado en TOTP, compatible con Google Authenticator, Authy y 1Password. Sin dependencias de pago.

- Activación desde el perfil: código QR, verificación de un código antes de encenderlo, y **códigos de recuperación de un solo uso** que se muestran una única vez y se pueden regenerar.
- El desafío de 2FA es una pantalla propia después de la contraseña, no un campo extra en el login.
- Opción «confiar en este dispositivo por 30 días» con cookie firmada, revocable desde sesiones activas.
- Desactivarlo exige confirmar la contraseña.
- Los códigos de recuperación se guardan hasheados, nunca en claro.
- Aunque hoy es opcional para todos, **deja el interruptor listo** para poder exigirlo por rol más adelante: es muy probable que el IYEM lo pida para el portal operativo cuando el sistema maneje cobros.

---

# FASE E — Aviso de privacidad y términos

Esto es requisito legal en México (LFPDPPP), no un detalle de forma.

- **En el registro:** una casilla **sin marcar por defecto** —marcarla de antemano invalida el consentimiento— con el texto «He leído y acepto el [aviso de privacidad] y los [términos y condiciones]», ambos como enlaces que abren en pestaña nueva. El formulario no envía sin ella.
- **En el ingreso con Google o Apple no hay formulario**, así que en el primer acceso muéstrale una pantalla de consentimiento antes de dejarlo entrar al portal. Sin aceptar, no se completa el alta.
- **Guarda constancia**, que es lo que realmente importa el día que alguien lo reclame: usuario, versión del documento aceptado, fecha y hora, e IP. Tabla propia, un registro por aceptación.
- **Versiona los documentos.** Cuando el IYEM publique una versión nueva, a quien tenga aceptada la anterior se le pide aceptar de nuevo al entrar. Por eso hay que guardar la versión, no un simple booleano.
- Enlaza a `route('privacidad')` y `route('terminos')`, que ya existen. **Aviso:** hoy esos textos están marcados `'provisional' => true` en `WelcomeController` pero publicados como definitivos (hallazgo BE-04). Si vas a hacer que la gente los acepte formalmente, **primero hay que validarlos con el IYEM**. Déjalo anotado como bloqueante.
- En el perfil, una sección donde la persona vea qué aceptó y cuándo, y pueda descargar sus datos o solicitar su baja (derechos ARCO).

---

# FASE F — Diseño de las pantallas

Todas las pantallas de acceso comparten un `AuthLayout.vue` nuevo, construido sobre el sistema «editorial técnico»: Carmen Sans en los titulares, GT Eesti en el cuerpo, amarillo `#FFE124` como acento, `tinta` y `cream` como fondos, las sombras y los easings ya definidos en `tailwind.config.js`.

**Composición partida.** A la izquierda un panel de marca a sangre —foto real del espacio con velo, o el amarillo de marca— con el logo, una frase corta y, abajo, una prueba social discreta: número de miembros, de talleres al mes, lo que Nódico quiera presumir. A la derecha, sobre fondo claro, el formulario centrado con ancho cómodo de lectura.

En iPhone el panel de marca se reduce a una banda superior con el logo; el formulario ocupa el resto. Nada de columnas apretadas.

**Detalles que hacen que se sienta bien hecho:**

- Botones sociales arriba, formulario abajo, separados por una línea con «o con tu correo».
- Etiquetas flotantes y foco marcado en amarillo, igual que el formulario de contacto de la landing, que ya está bien resuelto.
- Mostrar y ocultar contraseña con botón accesible, no con un icono suelto sin etiqueta.
- Estados completos: normal, foco, error, enviando con spinner y botón deshabilitado, y éxito.
- Errores del servidor debajo del campo que los provoca, nunca en un bloque genérico arriba.
- En iPhone los campos con `font-size` de al menos 16px o iOS hace zoom al enfocar, y `autocomplete` correcto en cada uno (`email`, `current-password`, `new-password`, `one-time-code`) para que funcionen el llavero y el autorrelleno.
- Área táctil de 44px mínimo, foco visible en todo, y respeto a `prefers-reduced-motion`.
- Enlace de vuelta al sitio siempre presente y con etiqueta legible.

**Pantallas a construir:**

| Pantalla | Notas |
|---|---|
| `Login` | Correo, contraseña, recordarme, enlace de recuperación, Google, enlace mágico, enlace a registro. |
| `Register` | Nombre, correo, contraseña con medidor, teléfono y ocupación opcionales, casilla legal, Google. |
| `ForgotPassword` | Un solo campo. Mensaje idéntico exista o no la cuenta. |
| `ResetPassword` | Contraseña nueva con medidor y confirmación. Avisar de que se cerrarán las demás sesiones. |
| `VerifyEmail` | Explica qué hacer, permite reenviar con cuenta regresiva, y ofrece corregir el correo si se escribió mal. |
| `ConfirmPassword` | Para acciones sensibles. Explica por qué se está pidiendo. |
| `TwoFactorChallenge` | Seis dígitos con avance automático entre casillas y pegado desde el portapapeles. Enlace a códigos de recuperación. |
| `TwoFactorSetup` | QR, clave manual, verificación y códigos de recuperación. |
| `MagicLinkSent` | Confirmación del envío con cuenta regresiva para reenviar. |
| `Consentimiento` | Solo para el primer acceso por Google o Apple. |
| `CuentaSuspendida` | Estado claro y vía de contacto. Sin bucle de redirección. |

---

# FASE G — Los dos portales

- **Operativo** (`/dashboard`): admin y staff, con permisos distintos. Caducidad de sesión más corta, bitácora visible, y 2FA recomendado con aviso visible en el panel mientras no esté activo.
- **Miembro** (`/portal`): cuenta propia, membresía, reservas, facturas, sesiones activas y datos personales.

Reglas:

- Después de entrar, cada quien va a su portal según su rol. Respeta el destino previsto (`intended`) solo si pertenece al portal que le corresponde: si un miembro tenía guardada una URL del panel operativo, se ignora.
- Un miembro que abra una URL del operativo recibe **403**, no una redirección. Lo contrario también.
- El menú de usuario del navbar público —el que se añadió en la portada— apunta al portal correcto según el rol.
- La separación es por servidor, no por interfaz: esconder un botón no es control de acceso. Cada ruta y cada acción se autoriza en el servidor con su Policy.

---

# FASE H — Pruebas y verificación

Escribe pruebas de verdad, no de adorno. Como mínimo:

- Registro, verificación de correo, ingreso, recuperación, restablecimiento y cierre de sesión, cada uno en su camino feliz y en el de error.
- Que un miembro **no** pueda entrar a ninguna ruta del panel operativo, y al revés.
- Que un usuario con rol desconocido reciba 403 y **no** entre en bucle (prueba de regresión de A.1).
- Que el límite de intentos por cuenta no bloquee a otras cuentas de la misma IP.
- Que el registro sin aceptar la casilla legal falle, y que la constancia se guarde con versión y fecha.
- Que dos cuentas no puedan vincularse a la misma identidad de Google.
- Que el flujo de OAuth rechace un `state` inválido.
- Que los códigos de recuperación de 2FA sean de un solo uso.
- Que no se pueda desvincular el último método de acceso.

Además: `npm run build` y `php artisan test` sin errores, consola del navegador limpia, recorrido completo solo con teclado, y prueba en 375 / 393 / 430 / 744 / 1024 / 1366 px. Corre `/security-review` sobre el diff completo.

---

# FASE I — Despliegue

Sigue `docs/DEPLOY.md`. Recuerda que el servidor de Hostinger no tiene Node —los assets se compilan en local— y que hay que sincronizar `public_html/build/` y `public_html/public/build/`.

Antes de subir, revisa que el `.env` del servidor tenga las claves de Google, `NODICO_APPLE_LOGIN_ENABLED=false` y el correo saliente funcionando: sin correo no hay verificación ni recuperación, y el registro queda inservible. **Prueba el envío real contra el servidor**, no solo en local.

Después del despliegue, comprueba en `prueba.nodico.com.mx`: registro completo con correo real, ingreso con Google, recuperación de contraseña, y que el enlace de verificación caduque.

---

## FORMA DE TRABAJAR

- Muéstrame el plan antes de empezar y espera mi visto bueno.
- Orden sugerido: Fase A → B → F (para ver ya las pantallas) → C → D → E → G → H → I.
- Enséñame las pantallas conforme las termines; el resto avánzalo sin consultarme cada detalle.
- No inventes textos legales. Si falta contenido, ponlo en pendientes y pregúntame.
- Todo en español, con acentos correctos.
- Si algo de seguridad choca con algo de experiencia de uso, dímelo con las dos opciones y tu recomendación en vez de decidirlo en silencio.
