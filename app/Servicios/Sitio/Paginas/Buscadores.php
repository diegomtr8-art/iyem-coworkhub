<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/**
 * Título y descripción de cada página para Google y para la tarjeta que sale
 * al compartir un enlace por WhatsApp (decisión 5). No se ven en la página,
 * pero un título mal puesto se hereda en cada enlace compartido: los topes son
 * el título, los 60 que Google enseña; la descripción admite algo más porque
 * la de hoy ya pasa de 160.
 *
 * El respaldo es config/nodico.php (`seo_paginas`), donde vivía esta copia.
 */
final class Buscadores
{
    private const PAGINAS = [
        'home'        => 'Inicio',
        'nosotros'    => 'Nosotros',
        'membresias'  => 'Membresías',
        'eventos'     => 'Salones (Eventos)',
        'actividades' => 'Comunidad (Actividades)',
        'privacidad'  => 'Aviso de privacidad',
        'terminos'    => 'Términos y condiciones',
    ];

    public static function secciones(): array
    {
        $secciones = [];

        foreach (self::PAGINAS as $ruta => $nombre) {
            $secciones["buscadores.{$ruta}"] = [
                'pagina'  => 'buscadores',
                'titulo'  => $nombre,
                'aparece' => 'En la pestaña del navegador, en el resultado de Google y en la tarjeta al compartir el enlace. A continuación del título se añade « — Nódico».',
                'ruta'    => $ruta,
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título', 60, fn () => config("nodico.seo_paginas.{$ruta}.titulo")),
                    'descripcion' => Campo::parrafo('Descripción', 220, fn () => config("nodico.seo_paginas.{$ruta}.descripcion"),
                        'Lo que Google enseña bajo el título. Suele cortar a partir de unos 155 caracteres: lo importante, al principio.'),
                ],
            ];
        }

        return $secciones;
    }
}
