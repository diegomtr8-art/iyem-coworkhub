<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/**
 * Lo que se repite en todas las páginas públicas: aliados, pie, «Hablemos» y
 * el bloque de Instagram. Se comparte con todas por HandleInertiaRequests.
 */
final class Comun
{
    public static function secciones(): array
    {
        return [
            // Los logos no se cambian desde el panel (decisión 3): cada uno está
            // recortado a su tinta y alineado a mano. Sí el nombre y el enlace.
            'comun.aliados' => [
                'pagina'  => 'comun',
                'titulo'  => 'Aliados',
                'aparece' => 'Franja «Con el respaldo de» de la portada y del pie de todas las páginas.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Encabezado', 40, 'Con el respaldo de'),
                    'iyem_nombre' => Campo::texto('IYEM · nombre', 80, 'Instituto Yucateco de Emprendedores',
                        'Es el texto alternativo del logo: lo que lee un lector de pantalla.'),
                    'iyem_url' => Campo::url('IYEM · enlace', 'https://iyem.yucatan.gob.mx', 'Vacío deja el logo sin enlace.', requerido: false),
                    'herencia_nombre' => Campo::texto('Herencia Viva · nombre', 80, 'Herencia Viva'),
                    'herencia_url' => Campo::url('Herencia Viva · enlace', 'https://www.herenciaviva.com', 'Vacío deja el logo sin enlace.', requerido: false),
                    'canieti_nombre' => Campo::texto('CANIETI · nombre', 80, 'CANIETI'),
                    // Sin enlace por indicación de Nódico; el campo queda por si cambia.
                    'canieti_url' => Campo::url('CANIETI · enlace', null, 'Hoy va sin enlace por indicación de Nódico.', requerido: false),
                ],
            ],
        ];
    }
}
