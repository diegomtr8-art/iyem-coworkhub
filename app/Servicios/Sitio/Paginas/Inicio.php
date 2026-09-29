<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/** La portada (`Welcome.vue`), sección por sección y en el orden del sitio. */
final class Inicio
{
    public static function secciones(): array
    {
        return [
            'inicio.hero' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Portada',
                'aparece' => 'Lo primero que se ve al entrar: el video, el titular y los datos del lugar.',
                'ancla'   => null,
                'campos'  => [
                    'antetitulo' => Campo::texto('Frase de arriba', 40, 'Bienvenidos al lugar', 'Línea amarilla sobre el titular.'),
                    'titulo'     => Campo::texto('Titular', 60, 'Donde el trabajo es un pretexto para crear',
                        'El texto más grande del sitio. Corto: a partir de unas seis palabras deja de caber en el celular.'),
                    'subtitulo'  => Campo::texto('Texto bajo el titular', 140, 'El coworking del Instituto Yucateco de Emprendedores en Mérida.'),
                    'video_youtube' => Campo::youtube('Video de YouTube', 'Ml4sprGUqzc',
                        'El código de 11 caracteres que va después de «v=» en el enlace del video. Es el que se reproduce de fondo y en «Ver el video completo».'),
                ],
            ],
            'inicio.servicios' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Servicios',
                'aparece' => 'Primera sección bajo la portada: el mosaico de servicios.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título de la sección', 60, 'Todo incluido en tu membresía'),
                    // Seis fijos: el mosaico es 2 grandes con foto + 4 compactos,
                    // y cada posición tiene su icono (decisión 2).
                    'elementos' => Campo::lista('Servicios', [
                        'titulo'      => Campo::texto('Título', 60, ''),
                        'descripcion' => Campo::parrafo('Descripción', 160, ''),
                    ], 6, 6, [
                        ['titulo' => 'Espacio colaborativo de trabajo', 'descripcion' => 'Escritorios en área abierta, con lugar para ti y para quien venga contigo.'],
                        ['titulo' => 'Sala profesional de creación de contenido', 'descripcion' => 'Estudio equipado para grabar tu podcast, tus reels o tus fotos de producto.'],
                        ['titulo' => 'Wifi con 200 MB de velocidad', 'descripcion' => 'Suficiente para videollamadas, subir contenido y trabajar sin pausas.'],
                        ['titulo' => 'Recepción de paquetería', 'descripcion' => 'Recibimos tus envíos aunque no estés; te avisamos en cuanto llegan.'],
                        ['titulo' => 'Hasta 5 invitados gratuitos al mes', 'descripcion' => 'Trae a tu equipo o a un cliente sin costo adicional.'],
                        ['titulo' => 'Café y agua todo el día', 'descripcion' => 'Barra libre mientras trabajas. Sin fichas ni límites.'],
                    ], 'Los dos primeros son las tarjetas grandes con foto; los otros cuatro, las compactas. Cada posición tiene su icono.', 'Servicio'),
                ],
            ],
            'inicio.espacios' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Espacios',
                'aparece' => 'Las cuatro tarjetas con foto de lo que se puede reservar.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título de la sección', 60, 'Lo que puedes reservar'),
                    'descripcion' => Campo::parrafo('Texto bajo el título', 200, 'Tu membresía incluye horas para usarlos. Reservas desde tu portal o desde la app.'),
                    // Número fijo hasta que cada tarjeta tenga su propia foto (4.4).
                    'elementos' => Campo::lista('Espacios', [
                        'nombre'      => Campo::texto('Nombre', 40, ''),
                        'cantidad'    => Campo::texto('Cantidad', 30, '', 'La etiqueta sobre la foto: «4 disponibles», «Cabina de podcast»…'),
                        'capacidad'   => Campo::texto('Capacidad', 30, ''),
                        'descripcion' => Campo::parrafo('Descripción', 160, ''),
                    ], 4, 4, [
                        ['nombre' => 'Cubículos privados', 'cantidad' => '4 disponibles', 'capacidad' => 'Hasta 4 personas',
                            'descripcion' => 'Para concentrarte, tomar una llamada o trabajar sin interrupciones.'],
                        ['nombre' => 'Sala de juntas', 'cantidad' => '1 disponible', 'capacidad' => 'Hasta 12 personas',
                            'descripcion' => 'Con pantalla, proyector y videoconferencia para recibir a tu equipo o a un cliente.'],
                        ['nombre' => 'Sala de creación de contenido', 'cantidad' => 'Cabina de podcast', 'capacidad' => 'Hasta 4 personas',
                            'descripcion' => 'Micrófonos, insonorización, aro de luz y fondo verde para grabar podcast o reels.'],
                        ['nombre' => 'Sala de fotografía', 'cantidad' => 'Estudio equipado', 'capacidad' => 'Hasta 6 personas',
                            'descripcion' => 'Luces profesionales y fondos removibles para fotografiar tu producto.'],
                    ], 'Los nombres y capacidades deberían coincidir con Configurar → Espacios.', 'Espacio'),
                ],
            ],
            'inicio.beneficios' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Beneficios',
                'aparece' => 'Los paneles que se abren sobre fondo oscuro.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título de la sección', 60, 'Y otras cosas que solo pasan aquí'),
                    // Número fijo hasta que cada panel tenga su propia foto (4.4).
                    // El color de cada panel es de la paleta y va por posición.
                    'elementos' => Campo::lista('Beneficios', [
                        'titulo'       => Campo::texto('Título', 60, ''),
                        'titulo_corto' => Campo::texto('Título corto', 20, '', 'Se lee en vertical cuando el panel está cerrado.'),
                        'descripcion'  => Campo::parrafo('Descripción', 160, ''),
                    ], 5, 5, [
                        ['titulo' => 'Descuentos en Tienda Herencia Viva', 'titulo_corto' => 'Descuentos',
                            'descripcion' => 'Precio preferente en artesanía yucateca, para ti y para los regalos de tu negocio.'],
                        ['titulo' => 'Directorio de miembros Nódico', 'titulo_corto' => 'Directorio',
                            'descripcion' => 'Tu proyecto visible ante toda la comunidad, y la comunidad disponible para ti.'],
                        ['titulo' => 'Acceso preferente a eventos y talleres', 'titulo_corto' => 'Eventos y talleres',
                            'descripcion' => 'Te avisamos antes y apartas lugar antes de que se abra al público.'],
                        ['titulo' => 'Conexión con el ecosistema emprendedor', 'titulo_corto' => 'Ecosistema',
                            'descripcion' => 'Programas del IYEM, CANIETI y la red de incubación, a un paso de tu escritorio.'],
                        ['titulo' => 'Espacio pet friendly', 'titulo_corto' => 'Pet friendly',
                            'descripcion' => 'Tu perro también tiene lugar aquí. Sin permisos ni explicaciones.'],
                    ], elemento: 'Beneficio'),
                ],
            ],
            'inicio.membresias' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Membresías',
                'aparece' => 'El carrusel de planes. Los planes y sus precios se cambian en Configurar → Planes.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título de la sección', 60, 'Elige tu plan ideal'),
                    'descripcion' => Campo::parrafo('Texto bajo el título', 240,
                        'El éxito comienza con el entorno correcto. Cada membresía te da la flexibilidad, los recursos y la comunidad que necesitas para hacer crecer tu proyecto.'),
                ],
            ],
            'inicio.daypass' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Day-pass gratuito',
                'aparece' => 'La banda amarilla con el sello giratorio, para emprendedores del interior del estado.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 40, 'Day-pass emprendedor'),
                    'titulo'   => Campo::texto('Titular', 70, '¿Eres emprendedor o artesano del interior del estado?'),
                    'sello'    => Campo::texto('Frase destacada', 50, 'Tu day-pass siempre es gratuito.', 'Va en el recuadro negro, ligeramente girado.'),
                    'texto'    => Campo::parrafo('Texto', 260,
                        'Si tu negocio está fuera de Mérida y necesitas un lugar para tener una junta, trabajar un rato o presentar tu proyecto, el espacio es tuyo sin costo.'),
                    'sello_giratorio' => Campo::texto('Texto del sello giratorio', 60, 'DAY-PASS GRATUITO · INTERIOR DEL ESTADO · ',
                        'Da la vuelta al círculo sobre la foto. Termínalo con « · » para que el final se junte bien con el principio.'),
                ],
            ],
            'inicio.salones' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Salones',
                'aparece' => 'La banda con la foto del salón. La ficha (medidas, capacidad, precio) se cambia en Configurar → Espacios.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título de la sección', 60, 'Espacios listos para tu evento'),
                    'descripcion' => Campo::parrafo('Texto bajo el título', 200,
                        'Talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida.'),
                ],
            ],
        ];
    }
}
