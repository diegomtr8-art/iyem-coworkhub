<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/**
 * `/actividades` (`Comunidad.vue`). Emprendedores y eventos tienen su propio
 * módulo; el calendario de Luma es configuración.
 */
final class Comunidad
{
    public static function secciones(): array
    {
        return [
            'comunidad.portada' => [
                'pagina'  => 'comunidad',
                'titulo'  => 'Portada',
                'aparece' => 'Arriba de la página, sobre la foto de la comunidad.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 30, 'Comunidad'),
                    'titulo'   => Campo::texto('Titular', 50, 'Aquí pasan cosas todo el mes'),
                    'imagen'   => Campo::imagen('Foto de portada', 'panoramica', Fotos::comunidadPortada(),
                        'Muy apaisada (2:1). Va oscurecida por abajo, donde está el texto.', decorativa: true),
                    'texto'    => Campo::parrafo('Texto', 260,
                        'Talleres, encuentros y una red de emprendedores que ya forman parte de los programas de incubación del Instituto Yucateco de Emprendedores.'),
                ],
            ],
            'comunidad.talleres' => [
                'pagina'  => 'comunidad',
                'titulo'  => 'Talleres',
                'aparece' => 'Junto al calendario de talleres.',
                'ancla'   => '#talleres',
                'campos'  => [
                    'titulo' => Campo::texto('Título', 60, 'Conoce los talleres del mes'),
                    'texto'  => Campo::parrafo('Texto', 400,
                        'En Nódico creemos que el conocimiento se multiplica cuando se comparte. Nuestros talleres están pensados para impulsar tu desarrollo profesional y personal, conectándote con expertos y otros emprendedores que, como tú, buscan transformar sus ideas en proyectos de impacto.'),
                ],
            ],
            'comunidad.directorio' => [
                'pagina'  => 'comunidad',
                'titulo'  => 'Directorio',
                'aparece' => 'Encima de las tarjetas de emprendimientos. Los emprendimientos se editan en Configurar → Emprendedores.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título', 60, 'Conoce a la comunidad'),
                    'descripcion' => Campo::parrafo('Texto bajo el título', 200,
                        'Emprendedores y empresas que forman parte o han egresado de nuestros programas de incubación del IYEM.'),
                ],
            ],
            'comunidad.teaser' => [
                'pagina'  => 'comunidad',
                'titulo'  => 'Invitación a los salones',
                'aparece' => 'La banda amarilla antes de «Hablemos».',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Titular', 50, '¿Organizas un evento?'),
                    'texto'  => Campo::parrafo('Texto', 200,
                        'Nuestros salones tienen capacidad para 120 personas, con proyector, sonido y mobiliario incluido.',
                        'Si cambia la capacidad en Configurar → Espacios, revisa este texto.'),
                ],
            ],
        ];
    }
}
