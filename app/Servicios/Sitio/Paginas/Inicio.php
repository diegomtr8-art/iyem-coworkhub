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
                ],
            ],
            'inicio.beneficios' => [
                'pagina'  => 'inicio',
                'titulo'  => 'Beneficios',
                'aparece' => 'Los paneles que se abren sobre fondo oscuro.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título de la sección', 60, 'Y otras cosas que solo pasan aquí'),
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
