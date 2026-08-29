<?php

namespace Database\Seeders;

use App\Models\AnuncioCoworking;
use App\Models\Checkin;
use App\Models\Comunicado;
use App\Models\Espacio;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin NODICO
        User::create([
            'name'      => 'Admin Nodico',
            'email'     => 'admin@nodico.com.mx',
            'password'  => Hash::make('Nodico2025!'),
            'tipo'      => 'admin',
            'empresa'   => 'NODICO',
            'telefono'  => '9991234567',
            'face_id_ok'=> true,
        ]);

        // Planes NODICO
        $planes = [
            [
                'nombre'              => 'Day-Pass',
                'subtitulo'           => 'Ideal para visitantes y ocasionales',
                'tipo'                => 'dia',
                'precio'              => 79.00,
                'horas_incluidas'     => 24,
                'dias_cowork_mes'     => 1,
                'horas_sala_mes'      => null,
                'horas_contenido_mes' => 1,
                'max_horas_sala_dia'  => null,
                'personas'            => 1,
                'max_reservas_mes'    => null,
                'acceso_24h'          => false,
                'color'               => '#F5C600',
                'destacado'           => false,
                'activo'              => true,
            ],
            [
                'nombre'              => 'Nodico Flex',
                'subtitulo'           => 'Para quienes necesitan flexibilidad',
                'tipo'                => 'mes',
                'precio'              => 249.00,
                'horas_incluidas'     => null,
                'dias_cowork_mes'     => 4,
                'horas_sala_mes'      => null,
                'horas_contenido_mes' => 4,
                'max_horas_sala_dia'  => null,
                'personas'            => 1,
                'max_reservas_mes'    => null,
                'acceso_24h'          => false,
                'color'               => '#1B1B2F',
                'destacado'           => false,
                'activo'              => true,
            ],
            [
                'nombre'              => 'Nodico PRO',
                'subtitulo'           => 'El más completo para profesionales',
                'tipo'                => 'mes',
                'precio'              => 599.00,
                'horas_incluidas'     => null,
                'dias_cowork_mes'     => null,
                'horas_sala_mes'      => 10,
                'horas_contenido_mes' => 10,
                'max_horas_sala_dia'  => 2,
                'personas'            => 1,
                'max_reservas_mes'    => null,
                'acceso_24h'          => true,
                'color'               => '#F5C600',
                'destacado'           => true,
                'activo'              => true,
            ],
            [
                'nombre'              => 'Nodico Match',
                'subtitulo'           => 'Para duplas y socios creativos',
                'tipo'                => 'mes',
                'precio'              => 799.00,
                'horas_incluidas'     => null,
                'dias_cowork_mes'     => null,
                'horas_sala_mes'      => 20,
                'horas_contenido_mes' => 15,
                'max_horas_sala_dia'  => 2,
                'personas'            => 2,
                'max_reservas_mes'    => null,
                'acceso_24h'          => true,
                'color'               => '#1B1B2F',
                'destacado'           => false,
                'activo'              => true,
            ],
        ];

        foreach ($planes as $p) Plane::create($p);

        // Espacios NODICO
        $espacios = [
            ['nombre' => 'Área de Coworking General',    'tipo' => 'coworking',  'capacidad' => 70, 'precio_hora' => 0,   'amenidades' => ['WiFi 200MB','Café y agua','Recepción de paquetes'], 'disponible' => true, 'piso' => 1],
            ['nombre' => 'Cubículo Privado 1',           'tipo' => 'privado',    'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['WiFi','Escritorio','Privacidad'], 'disponible' => true,  'piso' => 1],
            ['nombre' => 'Cubículo Privado 2',           'tipo' => 'privado',    'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['WiFi','Escritorio','Privacidad'], 'disponible' => true,  'piso' => 1],
            ['nombre' => 'Cubículo Privado 3',           'tipo' => 'privado',    'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['WiFi','Escritorio','Privacidad'], 'disponible' => true,  'piso' => 1],
            ['nombre' => 'Cubículo Privado 4',           'tipo' => 'privado',    'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['WiFi','Escritorio','Privacidad'], 'disponible' => true,  'piso' => 2],
            ['nombre' => 'Cubículo Privado 5',           'tipo' => 'privado',    'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['WiFi','Escritorio','Privacidad'], 'disponible' => true,  'piso' => 2],
            ['nombre' => 'Sala de Juntas Nodico',        'tipo' => 'sala_juntas','capacidad' => 12, 'precio_hora' => 0,   'amenidades' => ['WiFi','Proyector','Pantalla 85"','Videoconferencia','Café'], 'disponible' => true, 'piso' => 2],
            ['nombre' => 'Estudio Podcast / Contenido',  'tipo' => 'contenido',  'capacidad' => 4,  'precio_hora' => 0,   'amenidades' => ['Equipo de grabación','Insonorización','Iluminación profesional','WiFi'], 'disponible' => true, 'piso' => 1],
            ['nombre' => 'Estudio Fotografía',           'tipo' => 'fotografia', 'capacidad' => 6,  'precio_hora' => 0,   'amenidades' => ['Fondos removibles','Luces profesionales','Reflectores','WiFi'], 'disponible' => true, 'piso' => 1],
        ];

        foreach ($espacios as $e) Espacio::create($e);

        // Demo miembro
        $user = User::create([
            'name'       => 'María García',
            'email'      => 'miembro@nodico.com.mx',
            'password'   => Hash::make('password'),
            'tipo'       => 'miembro',
            'empresa'    => 'Freelance',
            'telefono'   => '9991112233',
            'ocupacion'  => 'Diseñadora gráfica',
            'face_id_ok' => true,
        ]);

        $planPro = Plane::whereIn('nombre', ['Nodico PRO', 'Nodo Pro'])->first();
        $inicio  = Carbon::now()->startOfMonth();
        $fin     = $inicio->copy()->addMonth()->subDay();

        $suscripcion = Suscripcion::create([
            'user_id'                => $user->id,
            'plan_id'                => $planPro->id,
            'fecha_inicio'           => $inicio,
            'fecha_fin'              => $fin,
            'estatus'                => 'Activa',
            'precio_pagado'          => $planPro->precio,
            'auto_renovar'           => true,
            'horas_sala_usadas'      => 3.5,
            'horas_contenido_usadas' => 2.0,
            'dias_usados'            => 0,
        ]);

        $folio = 'NOD-' . str_pad(1, 6, '0', STR_PAD_LEFT);
        Factura::create([
            'user_id'        => $user->id,
            'suscripcion_id' => $suscripcion->id,
            'folio'          => $folio,
            'concepto'       => 'Membresía ' . $planPro->nombre . ' — ' . $inicio->format('F Y'),
            'fecha'          => $inicio->toDateString(),
            'subtotal'       => round($planPro->precio / 1.16, 2),
            'iva'            => round($planPro->precio - ($planPro->precio / 1.16), 2),
            'total'          => $planPro->precio,
            'estatus'        => 'Pagada',
            'metodo_pago'    => 'Transferencia',
            'fecha_pago'     => $inicio->toDateString(),
        ]);

        $salaJuntas = Espacio::where('tipo', 'sala_juntas')->first();
        Reserva::create([
            'user_id'        => $user->id,
            'espacio_id'     => $salaJuntas->id,
            'suscripcion_id' => $suscripcion->id,
            'fecha'          => Carbon::today()->addDays(2),
            'hora_inicio'    => '10:00',
            'hora_fin'       => '12:00',
            'estatus'        => 'Confirmada',
            'precio_total'   => 0,
        ]);

        Comunicado::create([
            'user_id' => $user->id,
            'titulo'  => '¡Bienvenida a Nodico, María!',
            'mensaje' => 'Tu membresía Nodico PRO está activa. Tienes 10 horas en salas privadas y 10 horas en el estudio de contenido. ¡Disfruta el espacio!',
            'tipo'    => 'bienvenida',
            'leido'   => false,
        ]);

        // Anuncios
        AnuncioCoworking::create([
            'titulo'       => 'Taller de Emprendimiento — Junio',
            'contenido'    => 'Este mes tenemos un taller especial de pitch y financiamiento con expertos del IYEM. Cupo limitado para miembros.',
            'tipo'         => 'evento',
            'fecha_inicio' => Carbon::today(),
            'fecha_fin'    => Carbon::today()->addDays(20),
            'activo'       => true,
        ]);

        AnuncioCoworking::create([
            'titulo'       => 'Nuevo Estudio de Fotografía',
            'contenido'    => 'Estrenamos nuestro estudio de fotografía profesional con fondos intercambiables, luces y reflectores. ¡Reserva tu espacio!',
            'tipo'         => 'general',
            'fecha_inicio' => Carbon::today()->subDays(5),
            'fecha_fin'    => Carbon::today()->addDays(30),
            'activo'       => true,
        ]);

        // Contenido publico del sitio (precios, beneficios y salones).
        $this->call(NodicoWebSeeder::class);
    }
}
