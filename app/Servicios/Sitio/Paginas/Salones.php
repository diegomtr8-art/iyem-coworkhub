<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/**
 * `/eventos` (`Salones.vue`). Las fichas de las salas se editan en
 * Configurar → Espacios, y los precios del coffee break son configuración
 * (los usa el cotizador): aquí solo los textos.
 */
final class Salones
{
    public static function secciones(): array
    {
        return [
            'salones.portada' => [
                'pagina'  => 'salones',
                'titulo'  => 'Portada',
                'aparece' => 'Arriba de la página, sobre la foto del salón.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 30, 'Salones'),
                    'titulo'   => Campo::texto('Titular', 50, 'Espacios listos para tu evento'),
                    'imagen'   => Campo::imagen('Foto de portada', 'retrato', Fotos::salonesPortada()),
                    'texto'    => Campo::parrafo('Texto', 260,
                        'Nuestros salones están listos para tus talleres, conferencias o reuniones. Modernos, cómodos y equipados para que cada idea cobre vida.'),
                ],
            ],
            'salones.vacio' => [
                'pagina'  => 'salones',
                'titulo'  => 'Sin salones publicados',
                'aparece' => 'Solo se ve si no hay ningún salón publicado.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título', 60, 'Salones en actualización'),
                    'descripcion' => Campo::parrafo('Texto', 200,
                        'Estamos preparando la información de nuestros salones. Escríbenos y te compartimos disponibilidad y precios.'),
                ],
            ],
            'salones.coffee' => [
                'pagina'  => 'salones',
                'titulo'  => 'Coffee break',
                'aparece' => 'La banda oscura con los precios del coffee break. Los precios se cambian en la configuración del cotizador, no aquí.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título', 60, 'Coffee break para tu evento'),
                    'imagen' => Campo::imagen('Foto de fondo', 'ancha', Fotos::coffee(),
                        'Va casi cubierta por una capa oscura.', decorativa: true),
                ],
            ],
        ];
    }
}
