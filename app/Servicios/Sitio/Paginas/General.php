<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\CatalogoDelSitio;
use App\Servicios\Sitio\Campo;

/**
 * Datos del lugar que salen en todas las páginas: contacto, redes y la ficha
 * que leen Google y compañía. Su respaldo es config/nodico.php, así que el .env
 * de cada entorno sigue siendo el valor por omisión.
 */
final class General
{
    public static function secciones(): array
    {
        return [
            'contacto' => [
                'pagina'  => 'general',
                'titulo'  => 'Datos de contacto',
                'aparece' => 'Pie de todas las páginas, bloque «Hablemos» y franja inferior de la portada.',
                'ancla'   => '#hablemos',
                'campos'  => [
                    'email' => Campo::campoConReglas('email', 'Correo de contacto',
                        'También es a donde llegan los mensajes del formulario «Hablemos».',
                        ['required', 'string', 'email:rfc', 'max:120'],
                        fn () => config('nodico.contacto_email')),
                    'telefono' => Campo::campoConReglas('telefono', 'Teléfono',
                        'Diez dígitos, escrito como quieras que se lea. Al pulsarlo en el celular, marca.',
                        ['required', 'string', 'max:20', 'regex:/^[\d\s()+-]+$/', CatalogoDelSitio::telefonoDeDiezDigitos()],
                        fn () => config('nodico.telefono')),
                    'direccion' => Campo::parrafo('Dirección completa', 160, fn () => config('nodico.direccion'),
                        'Sale en el pie de página.'),
                    'direccion_corta' => Campo::texto('Dirección corta', 80, fn () => config('nodico.direccion_corta'),
                        'Sale en la portada y en «Hablemos», donde no cabe la completa.'),
                    'maps_url' => Campo::url('Enlace «Cómo llegar»', fn () => config('nodico.maps_url'),
                        'El enlace de Google Maps que da el botón «Compartir» de la ficha del lugar.',
                        hosts: CatalogoDelSitio::HOSTS['maps']),
                    'horarios' => Campo::texto('Horario', 60, fn () => config('nodico.horarios'),
                        'Solo es el texto que se lee. El horario en el que se puede reservar se cambia en la configuración del sistema.'),
                    'horarios_detalle' => Campo::texto('Nota del horario', 60, fn () => config('nodico.horarios_detalle'),
                        'Línea pequeña debajo del horario. Déjala vacía para quitarla.', requerido: false),
                ],
            ],

            // Vacío es válido en las tres redes: quita el icono del pie.
            'redes' => [
                'pagina'  => 'general',
                'titulo'  => 'Redes sociales',
                'aparece' => 'Iconos del pie de todas las páginas y sección de Instagram de la portada y de Comunidad.',
                'ancla'   => null,
                'campos'  => [
                    'instagram' => Campo::url('Instagram', fn () => config('nodico.redes.instagram'),
                        'Enlace al perfil. Vacío quita el icono.', requerido: false, hosts: CatalogoDelSitio::HOSTS['instagram']),
                    'facebook' => Campo::url('Facebook', fn () => config('nodico.redes.facebook'),
                        'Enlace a la página. Vacío quita el icono.', requerido: false, hosts: CatalogoDelSitio::HOSTS['facebook']),
                    'linkedin' => Campo::url('LinkedIn', fn () => config('nodico.redes.linkedin'),
                        'Enlace al perfil. Vacío quita el icono.', requerido: false, hosts: CatalogoDelSitio::HOSTS['linkedin']),
                    // Va dentro de la URL del iframe del perfil: solo los caracteres
                    // que Instagram admite en un nombre de usuario.
                    'instagram_usuario' => Campo::campoConReglas('usuario', 'Usuario de Instagram',
                        'Sin la @. Es la cuenta cuyas publicaciones se muestran en la portada y en Comunidad.',
                        ['required', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
                        fn () => config('nodico.instagram_handle')),
                ],
            ],

            // Lo que leen Google, WhatsApp y los mapas (JSON-LD). El horario de
            // apertura no está aquí a propósito: sale de nodico.operacion, que es
            // la regla del motor de reservas, para que no haya dos horarios.
            'negocio' => [
                'pagina'  => 'general',
                'titulo'  => 'Ficha para buscadores',
                'aparece' => 'No se ve en la página: es la ficha del negocio que leen Google y los mapas. El correo, el teléfono y las redes salen de las secciones de arriba.',
                'ancla'   => null,
                'campos'  => [
                    'descripcion' => Campo::texto('Descripción del negocio', 200,
                        'Coworking del Instituto Yucateco de Emprendedores en Mérida, Yucatán.'),
                    'calle' => Campo::texto('Calle y número', 120, 'Avenida Principal, Industrias No Contaminantes 13613'),
                    'colonia' => Campo::texto('Colonia', 80, 'Hacienda Sodzil Nte.',
                        'También sale como lugar de los eventos de Comunidad.'),
                    'localidad' => Campo::texto('Ciudad', 60, 'Mérida'),
                    'region' => Campo::texto('Estado', 60, 'Yucatán'),
                    'codigo_postal' => Campo::campoConReglas('texto', 'Código postal', null,
                        ['required', 'string', 'regex:/^\d{5}$/'], '97110'),
                    // Un dígito de más manda a Nódico al mar: solo coordenadas de
                    // la península de Yucatán.
                    'latitud' => Campo::numero('Latitud', 19.5, 21.7, 21.0527159,
                        'Sale de la ficha de Google Maps. Solo se aceptan coordenadas de la península.'),
                    'longitud' => Campo::numero('Longitud', -90.5, -86.7, -89.6413298),
                ],
            ],
        ];
    }
}
