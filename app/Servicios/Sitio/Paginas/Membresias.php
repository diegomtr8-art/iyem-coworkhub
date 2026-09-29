<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/** `/membresias` (`Membresias.vue`). Los planes en sí se editan en Configurar → Planes. */
final class Membresias
{
    public static function secciones(): array
    {
        return [
            'membresias.portada' => [
                'pagina'  => 'membresias',
                'titulo'  => 'Portada',
                'aparece' => 'La banda amarilla de arriba.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 30, 'Membresías'),
                    'titulo'   => Campo::texto('Titular', 40, 'Precios competitivos'),
                    'texto'    => Campo::parrafo('Texto', 260,
                        'Cuatro planes para etapas distintas: desde un día suelto hasta acceso ilimitado para dos personas. Todos incluyen comunidad, café y wifi.',
                        'Si cambia el número de planes en Configurar → Planes, revisa este texto.'),
                ],
            ],
            'membresias.vacio' => [
                'pagina'  => 'membresias',
                'titulo'  => 'Sin planes publicados',
                'aparece' => 'Solo se ve si no hay ningún plan publicado.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título', 60, 'Membresías en actualización'),
                    'descripcion' => Campo::parrafo('Texto', 200, 'Estamos afinando los planes. Escríbenos y con gusto te compartimos los precios vigentes.'),
                ],
            ],
            'membresias.incluido' => [
                'pagina'  => 'membresias',
                'titulo'  => 'Siempre incluido',
                'aparece' => 'La lista con palomitas, después de los planes.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'      => Campo::texto('Título de la sección', 60, 'Da igual el plan que elijas'),
                    'descripcion' => Campo::parrafo('Texto bajo el título', 200,
                        'Hay cosas que no dependen de la membresía: vienen con el simple hecho de ser parte de Nódico.'),
                ],
            ],
            'membresias.pasos' => [
                'pagina'  => 'membresias',
                'titulo'  => 'Cómo funciona',
                'aparece' => 'Los tres pasos sobre fondo oscuro.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título de la sección', 60, 'De la compra al escritorio'),
                    'nota'   => Campo::parrafo('Nota al pie', 240,
                        '¿Eres emprendedor o artesano del interior del estado? Tu day-pass siempre es gratuito: escríbenos y te damos acceso sin costo.',
                        'Línea pequeña bajo los tres pasos. Vacía no se muestra.', requerido: false),
                ],
            ],
        ];
    }
}
