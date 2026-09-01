<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Correo del formulario "Hablemos"
    |--------------------------------------------------------------------------
    */
    'contacto_email' => env('NODICO_CONTACTO_EMAIL', 'contacto@nodico.com.mx'),

    /*
    |--------------------------------------------------------------------------
    | Calendario de talleres (Luma)
    |--------------------------------------------------------------------------
    */
    'luma_embed' => env('NODICO_LUMA_EMBED', 'https://luma.com/embed/calendar/cal-ZE3dbDW6bLs4v7j/events?lt=dark'),

    /*
    |--------------------------------------------------------------------------
    | Datos de contacto públicos
    |--------------------------------------------------------------------------
    | Tomados de la ficha de Google Maps del lugar (2026-08-28).
    */
    'telefono'          => env('NODICO_TELEFONO', '999 461 5676'),
    'telefono_e164'     => env('NODICO_TELEFONO_E164', '+529994615676'),
    'direccion'         => env('NODICO_DIRECCION', 'Avenida Principal, Industrias No Contaminantes 13613, Hacienda Sodzil Nte., 97110 Mérida, Yuc.'),
    'direccion_corta'   => env('NODICO_DIRECCION_CORTA', 'Instituto Yucateco de Emprendedores'),
    'maps_url'          => env('NODICO_MAPS_URL', 'https://maps.app.goo.gl/zRrEqEoMLohqnwEx5'),
    // Embed sin clave de API, con las coordenadas de la ficha del lugar.
    'maps_embed'        => env('NODICO_MAPS_EMBED', 'https://maps.google.com/maps?q=21.0527159,-89.6413298&hl=es&z=16&output=embed'),
    'horarios'          => env('NODICO_HORARIOS', 'Lunes a viernes, 9:00 a 19:00 h'),
    'horarios_detalle'  => 'Sábados y domingos cerrado',

    /*
    |--------------------------------------------------------------------------
    | Redes sociales
    |--------------------------------------------------------------------------
    */
    'redes' => [
        'instagram' => 'https://www.instagram.com/nodicomx',
        'facebook'  => 'https://www.facebook.com/nodicomx',
        'linkedin'  => 'https://www.linkedin.com/in/nodico-club-de-emprendedores-316513377/',
    ],

    'instagram_handle' => 'nodicomx',

    /*
    |--------------------------------------------------------------------------
    | Video institucional
    |--------------------------------------------------------------------------
    */
    'video_id' => env('NODICO_VIDEO_ID', 'Ml4sprGUqzc'),

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    | `host_canonico` fija el origen de la canónica. Diego decidió el 29 de
    | agosto de 2026: **el sitio vive sin www**, o sea `https://nodico.com.mx`.
    |
    | Se deja por entorno en vez de fijo, porque staging tiene su propio host.
    | Si no se define, se usa `app.url`, que ya apunta al host correcto en cada
    | entorno; nunca el host de la petición, que es manipulable.
    */
    'host_canonico' => env('NODICO_HOST_CANONICO'),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    | Mientras esto sea `false`, la CSP se emite como `Report-Only`: el
    | navegador informa de lo que habria bloqueado, pero no bloquea nada. Una
    | CSP estricta rompe el sitio **en silencio**, y este incrusta YouTube,
    | Instagram, Luma y Google Maps.
    |
    | Para cerrarla: poner `NODICO_CSP_ESTRICTA=true` en staging, recorrer las
    | cinco publicas mas los dos portales con la consola abierta, y solo
    | entonces subirlo a produccion.
    */
    'csp_estricta' => env('NODICO_CSP_ESTRICTA', false),

    /*
    |--------------------------------------------------------------------------
    | Proveedores de acceso externo
    |--------------------------------------------------------------------------
    | Lo que enciende cada boton en las pantallas de acceso. Con el interruptor
    | apagado el boton **no se renderiza** y la ruta responde 404: no basta con
    | esconderlo en el front.
    |
    | **Apple no esta aqui a proposito.** No se implemento: el paquete oficial
    | `socialiteproviders/apple` no es instalable en este hosting —depende de
    | `lcobucci/jwt`, que exige `ext-sodium`, y esa extension no existe ni en el
    | CLI ni en el PHP-FPM de Hostinger— y Nodico tampoco tiene aun cuenta de
    | Apple Developer. Un interruptor que encendiera un boton sin flujo detras
    | seria peor que no tenerlo. Todo lo necesario para implementarlo el dia que
    | haga falta esta en docs/AUTH-PROVEEDORES.md.
    */
    'acceso' => [
        'google'        => (bool) env('NODICO_GOOGLE_LOGIN_ENABLED', false),
        'enlace_magico' => (bool) env('NODICO_ENLACE_MAGICO_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Segundo factor
    |--------------------------------------------------------------------------
    | Hoy es **opcional para todos**, como decidio Nodico, asi que la lista va
    | vacia. El interruptor queda listo porque es muy probable que el IYEM lo
    | exija para el portal operativo cuando el sistema maneje cobros: entonces
    | basta con poner ['admin', 'staff'] aqui.
    |
    | Con un rol en la lista, esas cuentas no pueden desactivar su segundo
    | factor. Lo que **no** hace todavia es obligar a activarlo a quien no lo
    | tenga: eso son pantallas de onboarding que hoy no existen.
    */
    'dos_factores' => [
        'obligatorio_para' => [],
    ],
    'sufijo_titulo' => 'Nódico',

    /*
    |--------------------------------------------------------------------------
    | Metadatos por página
    |--------------------------------------------------------------------------
    | Vivían dentro de cada componente Vue, y por tanto sólo existían después de
    | que el navegador ejecutara el JavaScript. WhatsApp, Slack, Telegram,
    | LinkedIn y Twitter/X no lo ejecutan: al compartir un enlace no veían ni
    | `og:title` ni `og:image`, así que no salía tarjeta.
    |
    | Ahora la copia vive aquí, el middleware la comparte y `app.blade.php` la
    | emite en el HTML. `Meta.vue` sigue encargándose de la navegación dentro
    | de la SPA, leyendo de la misma fuente para que no puedan divergir.
    |
    | La clave es el nombre de la ruta. `imagen` es el archivo de /img/og/ sin
    | extensión.
    */
    'seo_paginas' => [
        'home' => [
            'titulo'      => 'Coworking en Mérida para emprendedores',
            'descripcion' => 'Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida: espacio colaborativo, sala de creación de contenido, salones para eventos y una comunidad que impulsa tu proyecto.',
            'imagen'      => 'home',
        ],
        'nosotros' => [
            'titulo'      => 'Nosotros',
            'descripcion' => 'Más que un espacio físico, Nódico es una comunidad profesional en Mérida donde la colaboración, la vinculación estratégica y la formación continua convierten ideas en proyectos de impacto.',
            'imagen'      => 'nosotros',
        ],
        'membresias' => [
            'titulo'      => 'Membresías y precios',
            'descripcion' => 'Day-Pass, Nódico Flex, Nodo Pro y Nodo Match: elige la membresía de coworking que se ajusta a tu proyecto. Desde $79 MXN, con sala de creación de contenido, café y comunidad incluidos.',
            'imagen'      => 'membresias',
        ],
        'eventos' => [
            'titulo'      => 'Salones para eventos',
            'descripcion' => 'Renta los salones Yucatán Emprende de Nódico en Mérida: 15x14 m, hasta 120 personas, proyector, sonido, internet y mobiliario incluido desde $600 MXN por hora.',
            'imagen'      => 'eventos',
        ],
        'actividades' => [
            'titulo'      => 'Comunidad y actividades',
            'descripcion' => 'Talleres, eventos y el directorio de emprendedores de Nódico. Conoce las actividades del mes y a la comunidad que forma parte de los programas de incubación del IYEM.',
            'imagen'      => 'actividades',
        ],
        'privacidad' => [
            'titulo'      => 'Aviso de privacidad',
            'descripcion' => 'Cómo trata Nódico los datos personales de quienes usan el espacio y el sitio.',
            'imagen'      => 'home',
        ],
        'terminos' => [
            'titulo'      => 'Términos y condiciones',
            'descripcion' => 'Condiciones de uso del espacio, de las membresías y de la renta de salones de Nódico.',
            'imagen'      => 'home',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Operación del espacio y reglas de reserva
    |--------------------------------------------------------------------------
    | Reglas duras del motor de reservas. Estaban repartidas por controladores
    | y vistas —o directamente no existían—, que es como el sistema acabó
    | aceptando reservas de madrugada y a tres años vista.
    |
    | `dias_habiles` va en el formato de `Carbon::dayOfWeek`: 1 = lunes.
    | Coincide con el horario público de `nodico.horarios`; si uno cambia,
    | cambian los dos.
    */
    'operacion' => [
        'dias_habiles'          => [1, 2, 3, 4, 5],
        'apertura'              => env('NODICO_APERTURA', '09:00'),
        'cierre'                => env('NODICO_CIERRE', '19:00'),

        // Bloques de una hora, mínimo una hora por reserva. La reserva mínima es
        // de 1 h, así que los bloques van también de 1 h: mostrar medias horas que
        // luego no se pueden reservar solas confunde (decisión de Diego 31/08/2026).
        'granularidad_minutos'  => 60,
        'duracion_minima_horas' => 1,

        // No se reserva para dentro de 10 minutos ni con tres meses de antelación.
        'antelacion_minima_minutos' => 10,
        'antelacion_maxima_dias'    => 90,

        // Decisión de Nódico: se devuelven las horas si cancela con 2 h o más
        // de anticipación. Cancelación tardía y no-show consumen igual.
        'horas_para_cancelar_sin_penalizacion' => 2,

        // Ventana para consumir los días de los planes por día (Day-Pass, Flex).
        'dias_ventana_planes_por_dia' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Renta de salones (Fase 3.5)
    |--------------------------------------------------------------------------
    | Los salones Yucatán Emprende se rentan por hora y no consumen bolsa de
    | nadie. El coffee break tiene dos tramos conocidos y un hueco entre ellos
    | que Nódico decidió (01/09/2026) **cotizar a mano**: fuera de los tramos,
    | recepción escribe el precio por persona acordado.
    |
    | Estos valores son el punto de partida del cotizador; lo que se cobró queda
    | congelado en cada fila de `rentas_salon`, porque una cotización de hace
    | tres meses tiene que seguir diciendo su precio y no el de hoy.
    */
    'salones' => [
        'precio_hora' => env('NODICO_SALON_PRECIO_HORA', 600),

        // Tramos de coffee break: hasta_pax => precio por persona.
        // Entre 26 y 99 no hay tramo: el cotizador deja el precio editable.
        'coffee' => [
            ['hasta_pax' => 25,   'precio' => 45],
            ['desde_pax' => 100,  'precio' => 35],
        ],

        'anticipo_libre' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagos con referencia (transferencia / efectivo)
    |--------------------------------------------------------------------------
    |
    | La ruta que sí genera factura. El precio queda congelado hasta el
    | vencimiento. Los datos bancarios van AQUÍ, nunca incrustados en el código
    | (cambian): se llenan en el .env del servidor y quedan vacíos hasta que
    | Nódico los proporcione.
    */
    'pagos_referencia' => [
        'vencimiento_dias' => (int) env('NODICO_REFERENCIA_VENCIMIENTO_DIAS', 7),

        'banco'        => env('NODICO_BANCO', ''),
        'clabe'        => env('NODICO_CLABE', ''),
        'beneficiario' => env('NODICO_BENEFICIARIO', ''),
        'cuenta'       => env('NODICO_CUENTA', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona horaria del negocio
    |--------------------------------------------------------------------------
    |
    | Nódico está en Mérida (UTC-6, sin horario de verano). La app guarda los
    | instantes en UTC, pero el **día natural** del coworking se cuenta en esta
    | zona: quien entra a las 22:00 consume el día de hoy, no el de mañana.
    */
    'zona_horaria' => env('NODICO_TZ', 'America/Merida'),

];
