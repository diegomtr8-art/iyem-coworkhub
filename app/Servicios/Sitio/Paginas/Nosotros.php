<?php

namespace App\Servicios\Sitio\Paginas;

use App\Servicios\Sitio\Campo;

/** `/nosotros` (`Nosotros.vue`). */
final class Nosotros
{
    public static function secciones(): array
    {
        return [
            'nosotros.portada' => [
                'pagina'  => 'nosotros',
                'titulo'  => 'Portada',
                'aparece' => 'Arriba de la página, sobre la foto de la comunidad.',
                'ancla'   => null,
                'campos'  => [
                    'etiqueta' => Campo::texto('Etiqueta', 30, 'Nódico MX'),
                    'titulo'   => Campo::texto('Titular', 40, '¿Quiénes somos?'),
                    'texto'    => Campo::parrafo('Texto', 320,
                        'Más allá de un espacio físico, Nódico es una comunidad profesional donde se fomenta la colaboración, la vinculación estratégica y el desarrollo de habilidades a través de experiencias compartidas, eventos y formación continua.'),
                ],
            ],
            'nosotros.mision' => [
                'pagina'  => 'nosotros',
                'titulo'  => 'Misión',
                'aparece' => 'Junto a la foto de la mesa de trabajo.',
                'ancla'   => null,
                'campos'  => [
                    'titulo'   => Campo::texto('Título', 60, 'Convertir ideas en proyectos de impacto'),
                    'parrafo1' => Campo::parrafo('Primer párrafo', 320,
                        'Ser el espacio donde los emprendedores encuentran las herramientas, conexiones y experiencias necesarias para transformar sus ideas en proyectos de impacto.'),
                    'parrafo2' => Campo::parrafo('Segundo párrafo', 320,
                        'En Nódico impulsamos la creatividad, la colaboración y la innovación mediante espacios funcionales, contenido de valor y una comunidad vibrante que reta el pensamiento y promueve el crecimiento.',
                        'Déjalo vacío si basta con uno.', requerido: false),
                ],
            ],
            'nosotros.vision' => [
                'pagina'  => 'nosotros',
                'titulo'  => 'Visión',
                'aparece' => 'Sobre la foto oscurecida del espacio.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título', 60, 'El referente del sureste de México'),
                    'texto'  => Campo::parrafo('Texto', 400,
                        'Consolidarnos como el espacio referente en el sureste de México para el desarrollo de la creatividad, el emprendimiento y la innovación, reconocido por ser el punto de encuentro donde convergen las nuevas generaciones de creadores, emprendedores y agentes de cambio.'),
                ],
            ],
            'nosotros.valores' => [
                'pagina'  => 'nosotros',
                'titulo'  => 'Valores',
                'aparece' => 'Las tarjetas con icono al final de la página.',
                'ancla'   => null,
                'campos'  => [
                    'titulo' => Campo::texto('Título de la sección', 60, 'Lo que nos mueve'),
                ],
            ],
        ];
    }
}
