# Feed de Instagram

> Fase 4.G.3 (revisado 2026-08-31). El feed usa el **embed del perfil de
> Instagram**, igual que el sitio de Herencia Viva (que es un Odoo con el snippet
> `o_instagram_container`): un solo iframe a
> `https://www.instagram.com/{cuenta}/embed` que Instagram sirve con la cuadrícula
> de las últimas publicaciones y la cabecera del perfil.

## Cómo funciona

- Un único `<iframe>` en `InstagramSection.vue` apunta a
  `https://www.instagram.com/{handle}/embed`. El `handle` sale de
  `config('nodico.instagram')` (`nodicomx`).
- Se carga **de forma diferida** (IntersectionObserver): la sección va al final
  de cada página, así que el embed (~1 MB de terceros) no compite con la carga
  inicial; solo se pide cuando alguien se acerca. La altura queda reservada para
  que el layout no salte.
- No hace falta token, ni Graph API, ni caché, ni tarea programada: Instagram
  sirve el embed público directamente. La cuenta debe ser **pública** para que
  se vea sin iniciar sesión.

## Cambiar la cuenta

En `config/nodico.php`, la clave `instagram` (o `NODICO_INSTAGRAM` en el `.env`).
El embed y el enlace «Síguenos» se arman solos a partir de ese usuario.

## CSP

`frame-src` ya permite `https://www.instagram.com` (en `CabecerasDeSeguridad`),
que es lo único que el embed necesita.

## Nota

Se descartó el enfoque anterior con la Graph API (token de larga duración,
renovación por cron, reja curada de respaldo) a favor de este embed, más simple
y sin credenciales, por decisión de Nódico.
