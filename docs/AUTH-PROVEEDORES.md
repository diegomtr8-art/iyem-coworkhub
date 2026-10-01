# Proveedores de acceso externo

Qué hay implementado, qué falta y qué cuesta encender cada cosa.

| Proveedor | Estado | Interruptor |
|---|---|---|
| **Google** | Implementado. Apagado hasta tener credenciales. | `NODICO_GOOGLE_LOGIN_ENABLED` |
| **Enlace mágico** | Implementado y encendido. | `NODICO_ENLACE_MAGICO_ENABLED` |
| **Apple** | **No implementado.** Ver más abajo: hay un bloqueo del hosting. | — |
| Microsoft Entra ID | No implementado. Pendiente de preguntar al IYEM. | — |

Con el interruptor apagado, la ruta responde **404**, no 403: para quien pruebe
la URL, ese camino no existe. Esconder el botón en el front no es control de
acceso, y por eso el interruptor se comprueba en el servidor
(`OAuthController::asegurarQueEstaEncendido`) además de en el componente.

---

## Google

> **Estado al 1-oct-2026:** todo construido y **apagado**. El botón aparece
> solo si se cumplen **las dos** cosas: el interruptor
> `NODICO_GOOGLE_LOGIN_ENABLED=true` **y** las tres credenciales
> (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`). Encender
> el interruptor sin credenciales no dibuja un botón que truene: no se ve nada
> y la ruta responde 404 (`App\Support\AccesoConGoogle`,
> `tests/Feature/Auth/GoogleListoParaEncenderTest.php`).

### Qué pedirle a quien tenga la cuenta de Google Cloud del instituto

Nódico **no puede** crear estas credenciales: salen de la consola de Google
Cloud de una cuenta del IYEM. Este es el mensaje, tal cual, para la persona que
la administre:

1. **Usar o crear un proyecto** en https://console.cloud.google.com/ a nombre
   del IYEM (por ejemplo «Nódico»). Conviene que sea una cuenta institucional y
   no personal: si esa persona se va, las credenciales se quedan.
2. **APIs y servicios → Pantalla de consentimiento de OAuth**:
   - Tipo de usuario: **Externo**.
   - Nombre de la aplicación: **Nódico**. Logotipo: el de Nódico (opcional,
     pero si se sube, Google lo revisa).
   - Correo de asistencia: `contacto@nodico.com.mx`.
   - Dominios autorizados: `nodico.com.mx`.
   - Enlaces: página principal `https://nodico.com.mx`, política de privacidad
     `https://nodico.com.mx/aviso-de-privacidad`, términos
     `https://nodico.com.mx/terminos`.
   - Permisos (scopes): solo `openid`, `email` y `profile`. Son los básicos y
     **no requieren verificación** de Google.
   - Al terminar, **Publicar la aplicación** (pasar de *Testing* a
     *Producción*). En *Testing* solo pueden entrar las cuentas agregadas a mano
     como usuarios de prueba, y las demás ven un error de Google.
3. **APIs y servicios → Credenciales → Crear credenciales → ID de cliente de
   OAuth → Aplicación web**, con nombre «Nódico web»:
   - *Orígenes de JavaScript autorizados*: `https://prueba.nodico.com.mx` y
     `https://nodico.com.mx`.
   - *URI de redireccionamiento autorizados* (copiar exactos, sin barra final):
     `https://prueba.nodico.com.mx/acceso/google/retorno` y
     `https://nodico.com.mx/acceso/google/retorno`.
4. **Entregar** el *ID de cliente* (termina en `.apps.googleusercontent.com`) y
   el *secreto de cliente*. El secreto es una contraseña: por un canal privado
   (gestor de contraseñas o en persona), **nunca** por correo o chat abierto.
5. Solo si se va a usar en la **app móvil**: crear además un ID de cliente
   **iOS** (con el *bundle ID* de la app) y uno **Android** (con el nombre del
   paquete y la huella SHA-1 de la firma), y entregar los dos ID. Estos no
   tienen secreto.

### Qué hace falta (detalle técnico)

1. En [Google Cloud Console](https://console.cloud.google.com/), crear un
   proyecto (o usar el del IYEM).
2. **Pantalla de consentimiento de OAuth**: tipo «Externo», con el nombre de la
   aplicación, el correo de soporte y el dominio `nodico.com.mx`. Mientras esté
   en modo *Testing* solo entran las cuentas que se agreguen a mano; para abrirlo
   a cualquiera hay que **publicar** la app.
3. **Credenciales → ID de cliente de OAuth → Aplicación web**. Ahí se registran
   los *URI de redireccionamiento autorizados*, y tienen que coincidir **hasta el
   último carácter** con lo que manda Laravel:

   ```
   https://prueba.nodico.com.mx/acceso/google/retorno
   https://nodico.com.mx/acceso/google/retorno
   ```

   Un `http://` en vez de `https://`, una barra final de más o el `www.` puesto
   convierten esto en `redirect_uri_mismatch`, que es el error con el que
   tropieza todo el mundo la primera vez.

### Encenderlo

En el `.env` del servidor:

```ini
NODICO_GOOGLE_LOGIN_ENABLED=true
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://prueba.nodico.com.mx/acceso/google/retorno
```

Y después `php artisan config:cache`, **siempre**: con la caché vieja el
interruptor no cambia y parece que el código está roto.

`GOOGLE_REDIRECT_URI` cambia al pasar a producción
(`https://nodico.com.mx/acceso/google/retorno`); el ID y el secreto son los
mismos. Para la app móvil, además:

```ini
GOOGLE_CLIENT_ID_IOS=...apps.googleusercontent.com
GOOGLE_CLIENT_ID_ANDROID=...apps.googleusercontent.com
```

### Lo que ve la persona

- **Botón «Continuar con Google»** arriba del formulario en *Iniciar sesión* y
  en *Registrarse*, con el logotipo oficial de Google sin alterar sobre fondo
  blanco, como piden las reglas de marca de Google
  (https://developers.google.com/identity/branding-guidelines).
- **Cancela en la pantalla de Google**: vuelve al inicio de sesión con «Cancelaste
  el acceso con Google. Puedes entrar con tu correo y contraseña.»
- **Primera vez, sin cuenta**: se crea la cuenta con el correo ya verificado y se
  le pide aceptar el aviso de privacidad y los términos antes de entrar.
- **Ya tenía cuenta con contraseña y ese correo**: si Google confirma que el
  correo está verificado, se **vincula** a su cuenta (no se duplica) y se le
  avisa: «Listo: vinculamos tu cuenta de Google con tu cuenta de Nódico. Puedes
  seguir entrando también con tu correo y contraseña.» Si Google no lo confirma,
  no se vincula y se le pide entrar con su contraseña: así nadie se queda con la
  cuenta de otra persona registrando su correo en Google.
- **Tiene segundo factor**: Google no se lo salta; pasa al código igual.
- Desde *Mi seguridad* puede desvincular Google, siempre que le quede otra forma
  de entrar.

### Lo que ya está resuelto en el código

- **`state`** verificado contra la sesión. Nunca se usa `stateless()`: es la
  única defensa contra CSRF en este flujo y su ausencia es el fallo más común.
- **PKCE** activado (`enablePkce()`).
- **URI de retorno** compuesta con `route()`, jamás tomada de un parámetro de la
  petición: un `redirect_uri` que venga del request es un redirector abierto.
- **Lista blanca de proveedores**, por ruta (`whereIn`) y por controlador.
- **Vinculación solo con correo verificado.** Si Google no afirma
  `email_verified`, no se vincula a una cuenta existente. Sin esa comprobación,
  registrar una cuenta de Google con el correo de otra persona bastaría para
  quedarse con su cuenta de Nódico.
- **Cancelación y error** terminan en el login con un mensaje entendible.
- Quien entra con Google queda **verificado y sin contraseña**; puede ponerse
  una desde el perfil, sin obligación.
- **Nunca se desvincula el último método de acceso**: dejaría a la persona
  fuera de su cuenta sin ninguna vía de volver.

### Sobre `aud` e `iss`

El prompt pedía validarlos explícitamente. En este flujo **no hace falta código
propio**: Socialite usa el *authorization code flow*, en el que el `access_token`
se pide al endpoint de Google por HTTPS y el perfil se obtiene con ese token
contra `googleapis.com`. El `client_id` viaja en el intercambio y Google rechaza
el código si no corresponde. La validación de `aud`/`iss` es imprescindible
cuando se acepta un **`id_token` recibido del cliente** (por ejemplo desde una
app móvil), que no es el caso aquí. Si algún día Nódico publica app y manda
`id_token` al servidor, **entonces** hay que verificar firma, `aud`, `iss` y
`exp` antes de confiar en nada.

---

## Apple — no implementado, y por qué

**Sign in with Apple no está construido.** No es un olvido: hay un bloqueo del
hosting que no se puede sortear desde el repositorio.

### El bloqueo

`socialiteproviders/apple` es el paquete recomendado, pero **no es instalable en
este servidor**:

- Sus versiones actuales (5.8+) dependen de `lcobucci/jwt`, que **exige
  `ext-sodium`**.
- `ext-sodium` **no está disponible** ni en el PHP CLI del servidor (8.2.31) ni
  en tu XAMPP local. Comprobado el 29/08/2026 con `php -m`.
- Sus versiones anteriores usan `firebase/php-jwt` en rangos afectados por un
  **aviso de seguridad**, y Composer los bloquea con razón.

Para desbloquearlo: abrir un ticket a Hostinger pidiendo `ext-sodium` en el
plan. Es una extensión estándar de PHP; suele activarse sin problema.

### Lo que hay que saber antes de intentarlo

Estos son los puntos donde tropieza todo el mundo:

- Requiere el **Apple Developer Program**: **99 USD al año**. No hay nivel
  gratuito para Sign in with Apple en web.
- Hacen falta **cuatro cosas distintas**, y se confunden entre sí:
  1. Un **App ID** (identificador de la aplicación).
  2. Un **Services ID** — este es el `client_id` para web. **No es el App ID.**
     Es el error más repetido.
  3. Una **llave privada `.p8`** de Sign in with Apple. Se descarga **una sola
     vez**; si se pierde, hay que generar otra.
  4. El **Team ID** de la cuenta.
- El **`client_secret` de Apple no es una cadena fija**: es un **JWT firmado con
  la `.p8`** que **caduca a los seis meses como máximo**. Hay que generarlo y
  **renovarlo con una tarea programada**, o el acceso deja de funcionar un día
  sin previo aviso y sin que nada haya cambiado en el código.
- Apple **exige HTTPS y no acepta `localhost`**. Las pruebas van contra
  `prueba.nodico.com.mx`.
- Los dominios y las URL de retorno se **registran y verifican** en el portal de
  Apple antes de que nada funcione.
- **Apple envía el nombre de la persona solo en el primer ingreso.** Si no se
  guarda en ese momento, se pierde para siempre y la cuenta queda sin nombre.
  Hay que persistirlo en el mismo request en que llega.
- Apple ofrece **«Ocultar mi correo»**: llega una dirección de reenvío
  `@privaterelay.appleid.com`. **Es un correo válido y hay que aceptarlo.** Los
  mensajes a esa dirección funcionan, pero **solo desde dominios registrados en
  Apple**, así que `nodico.com.mx` tendría que darse de alta como *Email Source*
  o los correos de verificación no llegarían.
- Si algún día Nódico publica **app móvil** con otros accesos sociales, las
  reglas de la App Store **obligan** a ofrecer también Sign in with Apple.

### Lo que ya está preparado

La tabla `identidades_sociales` soporta Apple sin migrar nada: basta con
`proveedor = 'apple'`. El único compuesto `(proveedor, proveedor_id)` ya impide
que dos cuentas reclamen la misma identidad de Apple.

---

## Enlace mágico — implementado

Entrar sin contraseña con un enlace de un solo uso. Cero costo, cero dependencia
de terceros y muy buena conversión en móvil, que es donde está el público de
Nódico.

Las tres defensas que lo hacen aceptable:

1. **Un solo uso.** El token se marca gastado **antes** de abrir la sesión: si
   algo fallara después, no sirve para un segundo intento.
2. **Quince minutos.** Es una credencial completa que viaja por correo.
3. **Atado al navegador que lo pidió.** Al solicitarlo queda un secreto en la
   sesión y el enlace solo funciona desde ahí. Sin esto, un enlace reenviado o
   leído desde otro dispositivo sirve igual — la objeción clásica al mecanismo.

Además: el token se guarda **hasheado** (un enlace mágico en claro en la base es
una credencial de acceso completa), pedir uno nuevo invalida el anterior, y la
respuesta es idéntica exista o no la cuenta.

> **Pendiente para la fase D:** si la cuenta tiene segundo factor, el enlace
> mágico **no debe saltárselo**. Hoy no hay 2FA, así que no hay nada que saltar;
> al implementarla hay que enviar a `dos-factores.desafio` desde
> `EnlaceMagicoController::entrar` en vez de completar el acceso.

---

## Qué otro proveedor conviene — evaluación

### Microsoft Entra ID — vale la pena preguntarlo

El IYEM es una institución del gobierno de Yucatán y es muy probable que use
Microsoft 365. Si es así, **es el acceso ideal para el portal operativo**:
administración y recepción entrarían con su cuenta institucional, y las altas y
bajas de personal las gestionaría TI del instituto en un solo sitio — cuando
alguien deja el IYEM, pierde el acceso a Nódico automáticamente. Eso resuelve un
problema real que hoy no tiene solución: nadie se acuerda de dar de baja cuentas.

**Lo que hay que preguntarle a TI del IYEM:**

- ¿Usan Microsoft 365 / Entra ID?
- ¿Autorizan registrar una aplicación en su tenant?
- Tenant ID, y si aceptan restringir el acceso a un grupo concreto.

Cuesta poco más que Google: `socialiteproviders/microsoft-azure`, sin
dependencias problemáticas. Se implementa cuando haya respuesta.

### LinkedIn — bajo retorno

Coherente con el público emprendedor, pero casi nadie lo usa como método de
acceso; se asocia a publicar, no a entrar. El trabajo es el mismo que Google y
el uso sería marginal.

### Facebook — no

Mucha cobertura en México, pero la revisión de la app es lenta e impredecible, y
arrastra un costo de percepción en privacidad que no le conviene a una marca que
apenas se está presentando.

### SMS con código — no

Caro por mensaje y vulnerable a suplantación de SIM (*SIM swapping*). El NIST lo
desaconseja como segundo factor desde 2016. Ni como método principal ni como
segundo factor: para eso está el TOTP de la fase D, que es gratis y más seguro.

---

## Comprobaciones después de encender algo

```bash
# Con el proveedor apagado, la ruta no debe existir
curl -s -o /dev/null -w '%{http_code}\n' https://prueba.nodico.com.mx/acceso/google   # 404

# Encendido, debe redirigir a Google (302 hacia accounts.google.com)
curl -sI https://prueba.nodico.com.mx/acceso/google | grep -i location
```

Y en el navegador: entrar con Google desde una cuenta nueva, comprobar que la
cuenta queda verificada y **pendiente**, y que en «Mi seguridad» aparece la
identidad vinculada y **no** se puede desvincular si es el único acceso.
