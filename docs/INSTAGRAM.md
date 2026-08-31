# Feed de Instagram

> Fase 4.G.3. La cuenta **@nodicomx** es de empresa y está vinculada a una
> página de Facebook, así que el feed usa la **Instagram Graph API** (no el
> scraping ni el embed no soportado).

## Cómo funciona

- El feed se pide **en el servidor** (`App\Servicios\Instagram\FeedDeInstagram`),
  nunca desde el navegador: así el token no se expone.
- Se **cachea una hora** (`INSTAGRAM_CACHE_MIN`): la portada no llama a Meta en
  cada visita.
- Si la API falla o **no hay token, cae a la reja curada** de la tabla `ajustes`
  (clave `instagram_posts`, permalinks a mano). La sección nunca queda vacía ni
  muestra un error de Meta.

## Configuración

En `.env`:

```
INSTAGRAM_USER_ID=17841400000000000     # id de la cuenta de IG business
INSTAGRAM_TOKEN=EAAB...                  # token de larga duración (arranque)
INSTAGRAM_APP_ID=...                     # de la app de Meta, para renovar
INSTAGRAM_APP_SECRET=...
INSTAGRAM_CACHE_MIN=60
INSTAGRAM_AVISAR_A=diego@...             # a quién avisar si el token falla
```

El token vigente se guarda en `ajustes` (clave `instagram_token`) para que el
comando de renovación lo actualice sin tocar el `.env`; el del `.env` es solo el
valor de arranque.

## Cómo se generó el token (una vez, a mano)

1. En [developers.facebook.com](https://developers.facebook.com) crea una app de
   tipo *Business* y añade el producto **Instagram Graph API**.
2. Vincula la página de Facebook de Nódico y la cuenta de IG business @nodicomx.
3. Con el **Graph API Explorer**, genera un token de usuario con los permisos
   `instagram_basic`, `pages_show_list`, `pages_read_engagement`,
   `business_management`.
4. Cámbialo por uno de **larga duración** (60 días):
   `GET /oauth/access_token?grant_type=fb_exchange_token&client_id={app-id}&client_secret={app-secret}&fb_exchange_token={token-corto}`
5. Obtén el **IG user id**: `GET /me/accounts` → la página → `GET /{page-id}?fields=instagram_business_account`.
6. Pon el token y el id en el `.env`.

## Renovación automática

El token de larga duración **caduca a los ~60 días**. `nodico:renovar-token-instagram`
lo intercambia por uno nuevo y lo guarda en `ajustes`. Corre **cada semana** por
cron (`routes/console.php`), mucho antes del límite. **Si falla, avisa por
correo** a `INSTAGRAM_AVISAR_A`: es justo donde estos feeds se rompen meses
después sin que nadie lo note.

Para renovarlo a mano si hiciera falta:

```
php artisan nodico:renovar-token-instagram
```

## Permisos que exige

`instagram_basic`, `pages_show_list`, `pages_read_engagement` y
`business_management`. La app de Meta debe estar en modo *Live* y la página y la
cuenta de IG, correctamente vinculadas.

## Estado actual

Sin `INSTAGRAM_TOKEN` configurado, el sitio muestra la **reja curada** de
`ajustes`. Toda la infraestructura (servicio, caché, comando de renovación,
cabecera de perfil) queda lista: en cuanto se ponga el token, el feed pasa a ser
el real sin más cambios.
