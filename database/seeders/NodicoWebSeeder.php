<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use App\Models\DirectorioEmprendedor;
use App\Models\Espacio;
use App\Models\Plane;
use Illuminate\Database\Seeder;

/**
 * Contenido público de nodico.com.mx: precios, beneficios y salones.
 *
 * Idempotente — se puede volver a ejecutar sin duplicar registros:
 *   php artisan db:seed --class=NodicoWebSeeder
 */
class NodicoWebSeeder extends Seeder
{
    public function run(): void
    {
        $this->planes();
        $this->salones();
        $this->ajustes();
        $this->directorio();
    }

    /**
     * CNT-02 — directorio de emprendedores y destacado de la semana.
     * Antes vivian dentro de Comunidad.vue.
     */
    private function directorio(): void
    {
        $emprendedores = [
            [
                'nombre'    => 'Salabtún',
                'instagram' => null,
                'foto'      => '/img/nodico/emprendedor-semana-salabtun.webp',
                'descripcion' => 'Sal artesanal única de las charcas mayas de Celestún, Yucatán. Cosechada desde hace más de 600 años, combina tradición y naturaleza en un proceso heredado de generación en generación. Durante la temporada seca, los salineros recolectan delicadas hojuelas de sal, mientras los flamencos rosados ayudan a mantener limpio este ecosistema sagrado.',
                'destacado_semana' => true,
                'orden'     => 0,
            ],
            ['nombre' => 'Ahimsa Daram', 'instagram' => 'ahimsadaram', 'foto' => '/img/nodico/dir-ahimsa-daram.webp', 'orden' => 1],
            ['nombre' => 'Zentto',       'instagram' => 'zentto.mid',  'foto' => '/img/nodico/dir-zentto.webp',       'orden' => 2],
            ['nombre' => 'SaboReli',     'instagram' => 'saborelimx',  'foto' => '/img/nodico/dir-saboreli.webp',     'orden' => 3],
            ['nombre' => 'Kinimitas',    'instagram' => 'kinimitas',   'foto' => '/img/nodico/dir-kinimitas.webp',    'orden' => 4],
        ];

        foreach ($emprendedores as $datos) {
            DirectorioEmprendedor::updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos + ['activo' => true]
            );
        }
    }

    /**
     * Publicaciones de Instagram del perfil @nodicomx, verificadas una a una
     * contra su embed el 2026-08-28. Se guardan en BD para poder cambiarlas
     * desde el panel sin volver a desplegar.
     */
    private function ajustes(): void
    {
        Ajuste::updateOrCreate(
            ['clave' => 'instagram_posts'],
            [
                'descripcion' => 'Permalinks de publicaciones de @nodicomx que se muestran en /actividades',
                'valor' => [
                    'https://www.instagram.com/p/DV_WYmtFus1/',
                    'https://www.instagram.com/p/DUqe8D1kg9e/',
                    'https://www.instagram.com/p/DcjYCwqsNiF/',
                    'https://www.instagram.com/p/DcghxE_HLXw/',
                ],
            ]
        );
    }

    private function planes(): void
    {
        // 'alias' cubre los nombres con los que el plan pudo sembrarse antes,
        // para actualizar en lugar de duplicar.
        $planes = [
            [
                'alias'            => ['Day-Pass', 'Daypass'],
                'destacado'        => false,
                'nombre'           => 'Day-Pass',
                'orden'            => 1,
                'precio'           => 79.00,
                'periodo_label'    => 'por 1 día',
                'cta_label'        => 'Empezar Ahora',
                'color'            => '#EF7E88',
                'stripe_url'       => 'https://buy.stripe.com/00waER7JU4Lp65v0gb6Zy05',
                'descripcion_corta' => 'Espacio pensado para estudiantes, freelancers ocasionales o quienes necesitan trabajar por un día.',
                'descripcion_larga' => 'Ideal para estudiantes, freelancers ocasionales o personas que solo necesitan el espacio por un día. Perfecto para trabajar en un proyecto puntual.',
                'beneficios'       => [
                    'Acceso a espacio colaborativo',
                    '1 hora en sala de creación de contenido',
                    'Agua y café durante su estancia',
                ],
            ],
            [
                'alias'            => ['Nódico Flex', 'Nodico Flex', 'NODICO FLEX'],
                'destacado'        => false,
                'nombre'           => 'Nódico Flex',
                'orden'            => 2,
                'precio'           => 249.00,
                'periodo_label'    => 'por 4 días',
                'cta_label'        => 'Empezar Ahora',
                'color'            => '#FFDD00',
                'stripe_url'       => 'https://buy.stripe.com/6oUdR36FQ0v951r4wr6Zy04',
                'descripcion_corta' => 'Opción accesible para jóvenes emprendedores o estudiantes que necesitan el espacio por horas.',
                'descripcion_larga' => 'Pensada para jóvenes emprendedores, estudiantes o personas que solo necesitan entrar al espacio de cowork por unas horas y tener acceso a la comunidad emprendedora. Es una opción accesible para quienes están empezando y quieren conectar, trabajar un rato o explorar el ecosistema.',
                'beneficios'       => [
                    '4 días acceso al coworking',
                    '4 horas en sala de creación de contenido (1 por día)',
                    'Agua y café durante su estancia',
                ],
            ],
            [
                'alias'            => ['Nodo Pro', 'Nodico PRO', 'NODO PRO'],
                'destacado'        => true,   // la recomendada
                'nombre'           => 'Nodo Pro',
                'orden'            => 3,
                'precio'           => 599.00,
                'periodo_label'    => 'al mes',
                'cta_label'        => 'Contrata Ahora',
                'color'            => '#D6E265',
                'stripe_url'       => 'https://buy.stripe.com/6oU8wJggq91FeC1e716Zy03',
                'descripcion_corta' => 'Perfecta para emprendedores y creadores que buscan un espacio de trabajo constante.',
                'descripcion_larga' => 'Ideal para emprendedores activos, freelancers o creadores de contenido que necesitan un lugar de trabajo constante. Incluye asesoría, uso de salas y horas de creación de contenido.',
                'beneficios'       => [
                    'Acceso ilimitado al coworking',
                    '10 horas al mes en oficinas privadas y sala de juntas (2 horas por día)',
                    '10 horas al mes sala de creación de contenido',
                    '4 horas Asesor IYEM (1 hora por día)',
                    '20% Capacitaciones IYEM',
                    'Acceso libre a eventos de Cultura emprendedora',
                    'Acceso directo a programas del Instituto Yucateco de Emprendedores',
                    'Agua y café durante su estancia',
                ],
            ],
            [
                'alias'            => ['Nodo Match', 'Nodico Match', 'NODO MATCH'],
                'destacado'        => false,
                'nombre'           => 'Nodo Match',
                'orden'            => 4,
                'precio'           => 799.00,
                'periodo_label'    => 'por 1 mes',
                'cta_label'        => 'Empezar Ahora',
                'color'            => '#864B95',
                'stripe_url'       => 'https://buy.stripe.com/28EcMZc0agu73Xn6Ez6Zy02',
                'descripcion_corta' => 'Ideal para emprendedores, freelancers y creadores de contenido que requieren un espacio estable para trabajar.',
                'descripcion_larga' => 'Pensada para jóvenes emprendedores, ofrece una variedad más amplia de servicios y beneficios diseñados para impulsar el desarrollo de proyectos innovadores y fomentar la colaboración en un entorno dinámico.',
                'beneficios'       => [
                    'Acceso completo al coworking',
                    '20 horas al mes en oficinas privadas y sala de juntas (2 horas por día)',
                    '15 horas al mes en sala de creación de contenido (previa reserva en la aplicación)',
                    'Agua y café durante tu estancia',
                    'Acceso directo a programas del Instituto Yucateco de Emprendedores',
                    'Acceso libre a eventos de Cultura emprendedora',
                ],
            ],
        ];

        foreach ($planes as $datos) {
            $alias = $datos['alias'];
            unset($datos['alias']);

            $plan = Plane::whereIn('nombre', $alias)->first() ?? new Plane();

            // 'tipo' es obligatorio en la tabla; sólo se fija al crear.
            if (! $plan->exists) {
                $plan->tipo   = $datos['orden'] === 1 ? 'dia' : 'mes';
                $plan->activo = true;
            }

            $plan->fill($datos)->save();
        }
    }

    private function salones(): void
    {
        $incluye = [
            'Proyector.',
            'Sistema de sonido.',
            'Servicio de internet.',
            'Sillas.',
            'Mesas.',
            'Manteles.',
            'Base de micrófono.',
            'Extensiones.',
            'Adaptador HDMI.',
            'Mesa de registro.',
            'Pódium.',
        ];

        $descripcion = 'Esta sala para eventos ha sido diseñada para ofrecer un ambiente profesional, '
            . 'cómodo y funcional, ideal para reuniones, capacitaciones, presentaciones y actividades '
            . 'orientadas al desarrollo de ideas y negocios. Su equipamiento y distribución flexible '
            . 'permiten adaptarse a distintos tipos de montaje, brindando una experiencia completa '
            . 'para los emprendedores.';

        // Capacidades idénticas en ambas salas: ver discrepancia #1 en docs/AUDITORIA-NODICO.md.
        $salones = [
            ['nombre' => 'Yucatán Emprende 1', 'orden' => 1, 'imagen' => '/img/nodico/salon-yucatan-emprende-1.webp'],
            ['nombre' => 'Yucatán Emprende 2', 'orden' => 2, 'imagen' => '/img/nodico/salon-yucatan-emprende-2.webp'],
        ];

        foreach ($salones as $datos) {
            $salon = Espacio::where('nombre', $datos['nombre'])->first() ?? new Espacio();

            $salon->fill($datos + [
                'tipo'          => 'salon_eventos',
                'descripcion'   => $descripcion,
                'medidas'       => '15x14 metros',
                'precio_hora'   => 600.00,
                'capacidad'     => 120,
                'cap_herradura' => 45,
                'cap_mesas'     => 70,
                'cap_escuela'   => 54,
                'cap_auditorio' => 120,
                'incluye'       => $incluye,
                'disponible'    => true,
                'publicado'     => true,
                'piso'          => 1,
            ])->save();
        }
    }
}
