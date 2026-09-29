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
            'comun.instagram' => [
                'pagina'  => 'comun',
                'titulo'  => 'Instagram',
                'aparece' => 'El bloque con las publicaciones de Instagram, en la portada y en Comunidad. La cuenta se cambia en Datos generales → Redes sociales.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título', 50, 'Lo que pasa en Nódico'),
                ],
            ],
            'comun.hablemos' => [
                'pagina'  => 'comun',
                'titulo'  => 'Hablemos',
                'aparece' => 'El bloque de contacto con formulario y mapa, al final de cada página.',
                'ancla'   => '#hablemos',
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 30, 'Contacto'),
                    'titulo'   => Campo::texto('Título', 40, 'Hablemos'),
                    'texto'    => Campo::parrafo('Texto', 240,
                        '¿Quieres conocer el espacio, cotizar un salón o resolver una duda sobre las membresías? Escríbenos y te respondemos a la brevedad.'),
                ],
            ],
            'comun.pie' => [
                'pagina'  => 'comun',
                'titulo'  => 'Pie de página',
                'aparece' => 'La banda amarilla final y el pie oscuro de todas las páginas.',
                'ancla'   => null,
                'campos'  => [
                    'llamado_titulo' => Campo::texto('Llamado final · título', 50, '¿Listo para empezar?'),
                    'llamado_texto'  => Campo::parrafo('Llamado final · texto', 200,
                        'Elige tu membresía y trabaja desde el primer día en la comunidad emprendedora de Yucatán.'),
                    'marca' => Campo::parrafo('Texto junto al logo', 240,
                        'El coworking del Instituto Yucateco de Emprendedores en Mérida: espacio, comunidad y contenido para quienes están construyendo algo propio.'),
                    // El correo va en medio y lo pone el sistema (sale de
                    // Datos de contacto): nadie tiene que escribirlo ni enlazarlo.
                    'facturacion_antes' => Campo::texto('Facturación · antes del correo', 120, 'Para solicitar su factura, escriba a',
                        'Después de este texto sale el correo de contacto, ya enlazado.'),
                    'facturacion_despues' => Campo::texto('Facturación · después del correo', 160,
                        'con el asunto “Solicitud de factura”, incluyendo sus datos fiscales completos.'),
                    'leyenda' => Campo::parrafo('Leyenda legal', 200,
                        'Nódico es una marca registrada del Instituto Yucateco de Emprendedores. Todos los derechos reservados.',
                        'El «© año» se añade solo al final.'),
                ],
            ],

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
