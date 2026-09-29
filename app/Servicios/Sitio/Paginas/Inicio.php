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
                    'video_youtube' => Campo::youtube('Video de YouTube', 'Ml4sprGUqzc',
                        'El código de 11 caracteres que va después de «v=» en el enlace del video. Es el que se reproduce de fondo y en «Ver el video completo».'),
                ],
            ],
        ];
    }
}
