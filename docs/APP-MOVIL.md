# App móvil de Nódico — `app-movil/`

App nativa en **Expo SDK 57** (React Native 0.86, React 19.2), **TypeScript** y **Expo Router**. Consume la API de `docs/API-MOVIL.md`.

Tiene dos caras, según lo que diga el servidor del token (`usuario.cara`):

- **Miembro:** el portal completo, con pestañas Inicio · Reservar · Credencial · Membresía · Perfil.
- **Reportes** (administración): Resumen · Ocupación · Ingresos · Miembros. Todo en solo lectura.

---

## 1. Cómo arrancarla y verla en Expo Go

Hace falta la computadora con el servidor de Laravel y el teléfono **en la misma red wifi**.

**1. Servidor de Laravel** (en `C:\xampp\htdocs\coworkhub`):

```bash
php artisan serve --host=0.0.0.0 --port=8010
```

`0.0.0.0` hace que el teléfono pueda alcanzarlo. El puerto 8000 lo usa otro proyecto; por eso se usa el **8010**.

**2. Firewall de Windows.** La primera vez, permite a PHP en redes privadas cuando Windows lo pregunte. Si no lo preguntó, abre el puerto como administrador:

```powershell
New-NetFirewallRule -DisplayName "Nodico API 8010" -Direction Inbound -Protocol TCP -LocalPort 8010 -Action Allow -Profile Private
```

Para comprobarlo, abre `http://192.168.10.6:8010/api/v1/estado` desde el navegador del teléfono: tiene que responder un JSON.

**3. `.env` de la app** (`app-movil/.env`, hay un ejemplo en `.env.example`):

```
# Contra el servidor de pruebas (lo normal, una vez desplegada la API ahí):
EXPO_PUBLIC_API_URL=https://prueba.nodico.com.mx/api/v1
# Contra Laravel en esta computadora, con el teléfono en la misma wifi:
EXPO_PUBLIC_API_URL=http://192.168.10.6:8010/api/v1
```

Sin la variable, la app usa `https://prueba.nodico.com.mx/api/v1`. Los builds de EAS no leen este archivo: toman la URL de su perfil en `eas.json` (los tres perfiles apuntan al servidor de pruebas). Si la IP de la computadora cambia, se cambia aquí (`ipconfig` → «Dirección IPv4») y se reinicia `expo start`.

**Pagos con tarjeta:** el servidor local no tiene llaves de ninguna pasarela, así que ahí la opción «Tarjeta» sale deshabilitada. El pago con tarjeta se prueba contra el servidor de pruebas. La pasarela la decide el servidor (`PAGOS_PASARELA`, ver `docs/PAGOS-BBVA.md`):

- **Stripe:** la hoja de pago nativa. Stripe avisa del cobro (webhook) a `prueba.nodico.com.mx`, no a esta computadora.
- **BBVA:** la app abre el formulario del banco en el navegador del sistema (`expo-web-browser`, `openAuthSessionAsync`). Ahí se teclea la tarjeta y se pasa el 3-D Secure. BBVA regresa a `pago/bbva/regreso-app` en el servidor, que devuelve a la app (`nodico://regreso-banco`, o `exp://…` en Expo Go). La app pregunta por el cargo hasta que el servidor lo confirma con la API del banco. No hay SDK: el navegador del sistema, y no una WebView, porque algunos bancos bloquean el 3-D Secure dentro de vistas embebidas.

**4. Arrancar Expo:**

```bash
cd app-movil
npm install      # solo la primera vez
npx expo start --lan --port 8090   # el 8081 lo usa otro proyecto
```

**5. En el teléfono:** instala **Expo Go** desde la App Store o Google Play y escanea el QR. En iPhone se escanea con la cámara; en Android, desde Expo Go.

Si el teléfono no alcanza la computadora (redes con aislamiento de clientes, VPN), `npx expo start --tunnel` sirve el bundle de la app por túnel. La API sigue necesitando la IP local accesible, o la URL de staging en `EXPO_PUBLIC_API_URL`.

### El enlace mágico en desarrollo

El correo lleva un enlace que abre la app. En Expo Go la URL de la app no es `nodico://`, sino la que imprime `npx expo start` más la ruta, por ejemplo:

```
exp://192.168.10.6:8090/--/auth/enlace
```

Esa es la base que va en `NODICO_APP_URL_ENLACE` del `.env` de Laravel mientras se prueba en Expo Go. El servidor le añade `?token=…`. En una compilación propia es `nodico://auth/enlace`.

---

## 2. Qué se prueba en Expo Go y qué exige una compilación propia

Cada fila está verificada contra la documentación de Expo **SDK 57** (septiembre de 2026):

| Función | Expo Go | Compilación de desarrollo (EAS) | Fuente |
|---|---|---|---|
| Toda la interfaz, navegación, reservas, credencial QR, pagos con referencia, reportes | ✅ | ✅ | — |
| Token en almacén seguro (`expo-secure-store`) | ✅ | ✅ | [securestore](https://docs.expo.dev/versions/latest/sdk/securestore/) — «Included in Expo Go» |
| Brillo al máximo en la credencial (`expo-brightness`) | ✅ | ✅ | [brightness](https://docs.expo.dev/versions/latest/sdk/brightness/) — «Included in Expo Go» |
| Foto de perfil con cámara o galería (`expo-image-picker`) | ✅ | ✅ | [imagepicker](https://docs.expo.dev/versions/latest/sdk/imagepicker/) — «Included in Expo Go» |
| Descargar facturas y CSV y compartirlos (`expo-file-system`, `expo-sharing`) | ✅ | ✅ | [sharing](https://docs.expo.dev/versions/latest/sdk/sharing/) — «Included in Expo Go» |
| Datos sin conexión (`async-storage`, `netinfo`) | ✅ | ✅ | [async-storage](https://docs.expo.dev/versions/latest/sdk/async-storage/), [netinfo](https://docs.expo.dev/versions/latest/sdk/netinfo/) |
| Copiar referencia y CLABE (`expo-clipboard`) | ✅ | ✅ | [clipboard](https://docs.expo.dev/versions/latest/sdk/clipboard/) |
| **Pago con tarjeta — Stripe** (`@stripe/stripe-react-native`, PaymentSheet) | ✅ con tarjeta | ✅ | [stripe](https://docs.expo.dev/versions/latest/sdk/stripe/) — «Included in Expo Go». Solo con `PAGOS_PASARELA=stripe`. |
| **Pago con tarjeta — BBVA** (formulario del banco en `expo-web-browser`) | ✅ con tarjeta | ✅ | Sin SDK nativo: el formulario lo sirve BBVA. Solo con `PAGOS_PASARELA=bbva`. Sin probar aún contra el sandbox de BBVA. |
| Apple Pay / Google Pay | ❌ | ✅ | misma página: «Apple Pay is not supported in Expo Go», «Google Pay is not supported in Expo Go» |
| Bloqueo con **huella** (Android) | ✅ | ✅ | [local-authentication](https://docs.expo.dev/versions/latest/sdk/local-authentication/) |
| Bloqueo con **Face ID** (iPhone) | ⚠️ pide el código del teléfono en su lugar | ✅ | misma página: «FaceID authentication for iOS is not supported in Expo Go» |
| **Añadir reserva al calendario** (`expo-calendar`) | ❌ (el botón se oculta) | ✅ | [calendar](https://docs.expo.dev/versions/latest/sdk/calendar/) — «expo-calendar is currently unsupported in Expo Go» |
| Notificaciones **locales** (recordatorio 1 h antes de la reserva) | ✅ | ✅ | [notifications](https://docs.expo.dev/versions/latest/sdk/notifications/) — «Local notifications … remain available in Expo Go» |
| Notificaciones **push remotas**, Android | ❌ | ✅ | misma página: «unavailable in Expo Go on Android from SDK 53» |
| Notificaciones **push remotas**, iOS | ⚠️ la doc no lo prohíbe, pero el token de Expo necesita un proyecto de EAS (`projectId`), que todavía no existe | ✅ | misma página |
| **Ingresar con Google** | ❌ | ✅ | [google-authentication](https://docs.expo.dev/guides/google-authentication/) — «These libraries can't be used in Expo Go». **Pendiente:** el servidor ya lo acepta (`POST /auth/google`), pero la app todavía no tiene el botón ni la librería. |
| **Sign in with Apple** | ⚠️ funciona en iOS, con identificadores distintos a los de la app publicada | ✅ | [apple-authentication](https://docs.expo.dev/versions/latest/sdk/apple-authentication/). **Pendiente:** no está construido; será obligatorio en cuanto la app ofrezca Google, y el servidor lo tiene bloqueado por `ext-sodium` (ver `docs/AUTH-PROVEEDORES.md`). |

**Cómo trabajarlo:** toda la interfaz y la navegación se prueban en Expo Go, que es donde el ciclo es más rápido. Para lo nativo que falta —push remoto, Face ID, calendario, Google y Apple Pay— se pasa a una compilación de desarrollo:

```bash
npx eas-cli@latest build --profile development --platform android   # o ios
```

La compilación se instala igual de fácil (un enlace o un QR) y ya no tiene esos límites. Para iOS hace falta la cuenta de Apple Developer, que va **a nombre del instituto**.

---

## 3. Estructura

```
app-movil/
├── app.json                 nombre, esquema nodico://, permisos, íconos y plugins
├── .env / .env.example      EXPO_PUBLIC_API_URL
├── assets/
│   ├── fonts/               Carmen Sans (OTF) y GT Eesti (TTF), copiados de nodico/PAGINA WEB NÓDICO/Fonts
│   └── images/              logo blanco y oscuro, ícono y splash generados con la marca
└── src/
    ├── app/                 rutas (Expo Router)
    │   ├── _layout.tsx      proveedores y rutas protegidas por fase de sesión
    │   ├── bloqueo.tsx      cuenta suspendida · correo sin verificar · consentimiento
    │   ├── (acceso)/        entrar, dos factores, enlace mágico, planes y auth/enlace (destino del enlace)
    │   ├── (miembro)/       pestañas + pantallas secundarias + hojas (formSheet)
    │   └── (reportes)/      la cara de administración
    ├── componentes/         Texto, Boton, Anillo, Esqueleto, EstadoVacio, Pantalla, Selector…
    ├── lib/
    │   ├── api.ts           único cliente HTTP: cabeceras del contrato y errores (§4.3)
    │   ├── almacen.ts       almacén seguro: token, id del dispositivo, credencial, secreto del enlace
    │   ├── sesion.tsx       fases, bloqueos, biometría, entrar y salir
    │   ├── consultas.ts     TanStack Query con persistencia sin conexión
    │   ├── nativo.ts        calendario, compartir, descargas y push
    │   └── tipos.ts         formas del contrato de la API
    └── tema/                tokens de color, espaciado y tipografía; siempre oscuro
```

### Decisiones que conviene conocer

- **Una sola puerta a la API** (`lib/api.ts`). Añade `Authorization`, `X-App-Version`, `X-App-Plataforma` y, en las escrituras que no deben duplicarse, `Idempotency-Key`. La clave se genera una vez por acción y se reutiliza en los reintentos por falta de red. Si el servidor rechaza la petición, se renueva.
- **Errores que cambian el rumbo** (`no_autenticado`, `cuenta_suspendida`, `correo_sin_verificar`, `consentimiento_pendiente`, `version_obsoleta`): los recoge `lib/sesion.tsx`, venga de la pantalla que venga, y llevan a su pantalla explicada. Un `422` se pinta debajo del campo al que se refiere.
- **Nada sensible fuera del almacén seguro.** El token, la credencial y el secreto del enlace mágico viven en `expo-secure-store`. La caché sin conexión (AsyncStorage) **excluye** datos fiscales, reportes y dispositivos (`meta.persistir = false`).
- **El servidor decide.** Reservar anticipa el tope diario y el saldo con los números de la API, y el aviso usa el mismo texto que devolvería el servidor. Aun así, al confirmar manda lo que responda el servidor. Lo mismo con «¿devuelve horas?» al cancelar: se recalcula con `limite_cancelacion` para que el aviso cambie a tiempo, pero manda la respuesta del servidor.
- **Permiso de notificaciones** solo después de la primera reserva confirmada, nunca al abrir la app.
- **Hojas deslizantes nativas.** Se usa `presentation: 'formSheet'` de Expo Router (`react-native-screens`) en lugar de `@gorhom/bottom-sheet`: es nativa en las dos plataformas, viene en Expo Go y no añade otra dependencia sobre Reanimated.
- **La credencial no se comparte.** Es un código que identifica a la persona en recepción; mandarlo por mensaje sería regalarlo. Lo que sí se comparte son las reservas.

---

## 4. Sistema visual

La marca de Nódico traducida al teléfono (`src/tema/tokens.ts`):

**La app es negra siempre** y usa solo la paleta oficial de Nódico, la misma de la web (`tailwind.config.js`). No sigue el modo claro del sistema: `app.json` fija `userInterfaceStyle: "dark"`.

| Token | Color | Uso |
|---|---|---|
| `fondo` | tinta `#1A1918` | Fondo de la app |
| `superficie` / `superficieAlta` | dark `#2E2D2C` / `#3D3C3A` | Tarjetas, listas, campos |
| `texto` / `textoSuave` | crema `#F4F1EA` / `#E8E1D1` | Texto principal y secundario |
| `acento` | amarillo `#FFE124` | Único acento fuerte: botones, selección, pestaña activa, anillo de salas |
| `coral` | `#EF7E88` | Estudio de contenido, problemas, no-show |
| `lima` | `#D6E265` | Asesoría, estados «bien» (solo sobre oscuro) |
| `morado` | `#864B95` | Solo como relleno con texto blanco; sobre negro no alcanza el contraste para texto o gráficas |

- **Tipografía:** Carmen Sans (Heavy, ExtraBold, Bold y SemiBold) para números y títulos; GT Eesti para el texto. Los números grandes —horas restantes, días que faltan— usan Carmen Sans Heavy de 32 a 56 pt.
- **Espaciado generoso:** márgenes laterales de 24 pt, tarjetas con 24 pt de relleno y radios de 20 pt.
- **Limpia y accesible:** listas agrupadas (`Grupo` + `Fila`) en lugar de tarjetas con bordes; títulos de sección en frase normal, sin mayúsculas espaciadas; un solo acento; contraste AA en todo el texto; el texto crece con el tamaño de letra del sistema (con tope); las pestañas llevan nombre; lector de pantalla con etiquetas completas; las animaciones respetan «reducir movimiento».
- **Lo que la hace sentirse nativa:** anillos SVG animados con Reanimated en Inicio; hojas nativas para detalles; háptica al elegir, confirmar y cancelar; esqueletos que respiran en lugar de indicadores giratorios; deslizar para refrescar; deslizar una reserva para cancelarla; estados vacíos con ilustración y acción; gesto de regresar nativo; respeto del notch y la barra de gestos.

---

## 5. Verificación

| Comprobación | Resultado |
|---|---|
| `npx tsc --noEmit` | sin errores |
| `npx expo lint` (incluye las reglas del React Compiler) | sin errores |
| `npx expo-doctor` | 21/21 comprobaciones |
| `npx expo export --platform android` | bundle generado (5.5 MB) |
| `npx expo export --platform ios` | bundle generado (5.3 MB) |

Queda pendiente probarla en un iPhone y un Android de verdad contra la API local.
