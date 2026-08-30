<?php

namespace Database\Seeders;

use App\Enums\BolsaDeHoras;
use App\Enums\EstadoAsesoria;
use App\Enums\EstadoCuenta;
use App\Enums\MotivoMovimiento;
use App\Enums\RolUsuario;
use App\Support\DocumentosLegales;
use App\Models\AnuncioCoworking;
use App\Models\Asesor;
use App\Models\Checkin;
use App\Models\DatosFiscales;
use App\Models\DirectorioEmprendedor;
use App\Models\Espacio;
use App\Models\Evento;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Membresias\GestorDeMembresias;
use App\Servicios\Reservas\RegistroDeBloques;
use App\Servicios\Reservas\ReservaOperativa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fase 4.F — datos de demostración: deja Nódico como si llevara meses operando,
 * para poder verlo funcionando antes de conectar el Face ID.
 *
 * ⛔ NO es el seeder de producción. Se niega a correr si APP_ENV=production
 * (ver el guardia al principio de run()). Un seeder de demostración sobre datos
 * reales es un desastre irreversible.
 *
 * Todo se construye por los **servicios reales** —altas, libro de horas,
 * reservas—, no escribiendo a mano en las tablas: así los saldos cuadran y la
 * demo prueba de paso que las invariantes se sostienen. Los usuarios de demo
 * llevan el sufijo `.demo@nodico.com.mx`, y el seeder los borra y rehace en
 * cada pasada para poder repetirlo.
 *
 * Qué NO puede sembrar todavía, porque su estructura llega en fases posteriores
 * (queda anotado en docs/DEMOSTRACION.md):
 *   - Day-pass gratuito del interior con municipio y giro  → Fase E.
 *   - Catálogo de temas de asesoría (aquí el tema es texto) → Fase D.
 *   - Catálogo de emprendimientos con rotación del destacado → Fase H.
 *
 * Las horas de asesoría por plan (Nodo Pro y Match, 4 h/mes) las define el
 * seeder base (NodicoWebSeeder), no este.
 */
class DemoSeeder extends Seeder
{
    private GestorDeMembresias $membresias;
    private ReservaOperativa $reservas;
    private LibroDeHoras $libro;
    private RegistroDeBloques $bloques;

    private User $recepcion;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'DemoSeeder no corre en producción. Son datos de prueba y '
                . 'arrasarían con lo real. Aborta.'
            );
        }

        $this->membresias = app(GestorDeMembresias::class);
        $this->reservas   = app(ReservaOperativa::class);
        $this->libro      = app(LibroDeHoras::class);
        $this->bloques    = app(RegistroDeBloques::class);

        $this->limpiarDemoAnterior();

        $this->sembrarEquipo();
        $this->sembrarMiembroEstrella();
        $this->sembrarOtrosMiembros();
        $this->llenarLaAgenda();
        $this->sembrarCatalogos();

        $this->command?->info('DemoSeeder: sistema poblado. Accesos en docs/DEMOSTRACION.md.');
    }

    // ── Preparación ──────────────────────────────────────────────────────────

    private function limpiarDemoAnterior(): void
    {
        // Las FK son cascadeOnDelete: al borrar los usuarios de demo se van con
        // ellos sus suscripciones, reservas, bloques, movimientos y check-ins.
        User::where('email', 'like', '%.demo@nodico.com.mx')->get()
            ->each(fn (User $u) => $u->delete());
    }

    /**
     * Crea un usuario de demo **sin factory**: en staging se corre con
     * `composer install --no-dev`, así que Faker no existe y `User::factory()`
     * revienta. `tipo` y `estado_cuenta` van por `forceFill` porque no son
     * asignables en masa a propósito. Se le deja el consentimiento aceptado
     * para que el middleware no lo frene en la demostración.
     */
    private function crearUsuario(array $attrs, RolUsuario $rol = RolUsuario::Miembro, EstadoCuenta $estado = EstadoCuenta::Activa): User
    {
        $usuario = new User();
        $usuario->forceFill(array_merge([
            'password'          => 'demo1234', // el cast 'hashed' lo cifra.
            'email_verified_at' => now(),
        ], $attrs, [
            'tipo'          => $rol->value,
            'estado_cuenta' => $estado->value,
        ]))->save();

        foreach (app(DocumentosLegales::class)->versiones() as $documento => $version) {
            $usuario->consentimientos()->create([
                'documento'   => $documento,
                'version'     => $version,
                'aceptado_en' => now(),
                'ip'          => '127.0.0.1',
            ]);
        }

        return $usuario;
    }

    // ── Usuarios ─────────────────────────────────────────────────────────────

    private function sembrarEquipo(): void
    {
        $this->crearUsuario([
            'name'  => 'Admin (demo)',
            'email' => 'admin.demo@nodico.com.mx',
            'empresa'  => 'NODICO',
        ], RolUsuario::Admin);

        $this->recepcion = $this->crearUsuario([
            'name'  => 'Recepción (demo)',
            'email' => 'recepcion.demo@nodico.com.mx',
            'empresa'  => 'NODICO',
        ], RolUsuario::Staff);
    }

    private function sembrarMiembroEstrella(): void
    {
        $ana = $this->crearUsuario([
            'name'      => 'Ana Pro (demo)',
            'email'     => 'pro.demo@nodico.com.mx',
            'empresa'   => 'Estudio Aracena',
            'ocupacion' => 'Arquitecta',
            'telefono'  => '9990000001',
        ]);

        // Alta de Nodo Pro hace 20 días: el ciclo vigente ya lleva historial.
        $plan = Plane::where('nombre', 'Nodo Pro')->firstOrFail();
        $inicio = CarbonImmutable::today()->subDays(20)->toDateString();
        $suscripcion = $this->membresias->alta($ana, $plan, $this->recepcion, $inicio, 599.00, 'Alta de demostración');

        // Reservas pasadas confirmadas, con su check-in.
        $r1 = $this->reservar($ana, 'sala_juntas', $this->habil(-6), '10:00', '12:00');
        $this->checkinDe($r1, $this->habil(-6), '10:02', '11:55');

        $r2 = $this->reservar($ana, 'contenido', $this->habil(-4), '16:00', '18:00');
        $this->checkinDe($r2, $this->habil(-4), '16:05', '17:50');

        // Un no-show: reservó, no llegó. Las horas se consumen igual.
        $noShow = $this->reservar($ana, 'sala_juntas', $this->habil(-3), '17:00', '18:00');
        $this->marcarNoShow($noShow);

        // Una cancelación **tardía** (sin antelación): no devuelve.
        $tardia = $this->reservar($ana, 'fotografia', $this->habil(-2), '10:00', '11:00');
        $this->cancelar($tardia, devuelve: false);

        // Reserva futura confirmada.
        $this->reservar($ana, 'sala_juntas', $this->habil(3), '10:00', '12:00');

        // Una cancelación **con antelación**: devuelve las horas.
        $devuelta = $this->reservar($ana, 'contenido', $this->habil(5), '15:00', '16:00');
        $this->cancelar($devuelta, devuelve: true);

        // Asesoría IYEM realizada (histórica) y una pendiente en la bandeja.
        $this->asesoriaRealizada($suscripcion, 'Modelo de negocio y validación de mercado', $this->habil(-5));
        app(\App\Servicios\Asesorias\GestorDeAsesorias::class)->solicitar(
            $suscripcion,
            'Precios y costos para mi taller de arquitectura',
            $this->habil(4),
            'Por la mañana',
        );

        // Datos fiscales completos.
        DatosFiscales::create([
            'user_id'           => $ana->id,
            'rfc'               => 'AARA850312QK8',
            'razon_social'      => 'ANA ARACENA RIVERO',
            'regimen_fiscal'    => '612',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97000',
            'email_facturacion' => 'facturas.ana@estudioaracena.mx',
        ]);

        // Cobros de las mensualidades.
        $this->facturaMensual($ana, $suscripcion, $this->habil(-20));
        $this->facturaMensual($ana, $suscripcion, $this->habil(10));
    }

    private function sembrarOtrosMiembros(): void
    {
        // Nodo Match activa.
        $beto = $this->crearUsuario([
            'name' => 'Beto Match (demo)', 'email' => 'match.demo@nodico.com.mx',
            'empresa' => 'Dúo Creativo', 'telefono' => '9990000002',
        ]);
        $this->membresias->alta($beto, Plane::where('nombre', 'Nodo Match')->firstOrFail(),
            $this->recepcion, CarbonImmutable::today()->subDays(10)->toDateString());

        // Nódico Flex activa (por días), con algún check-in.
        $carla = $this->crearUsuario([
            'name' => 'Carla Flex (demo)', 'email' => 'flex.demo@nodico.com.mx',
            'empresa' => 'Freelance', 'telefono' => '9990000003',
        ]);
        $this->membresias->alta($carla, Plane::where('nombre', 'Nódico Flex')->firstOrFail(),
            $this->recepcion, CarbonImmutable::today()->subDays(8)->toDateString());
        $this->checkinLibre($carla, $this->habil(-5), '09:15', '14:00');
        $this->checkinLibre($carla, $this->habil(-1), '10:00', '13:30');

        // Day-Pass.
        $dani = $this->crearUsuario([
            'name' => 'Dani Day-Pass (demo)', 'email' => 'daypass.demo@nodico.com.mx',
            'telefono' => '9990000004',
        ]);
        $this->membresias->alta($dani, Plane::where('nombre', 'Day-Pass')->firstOrFail(),
            $this->recepcion, CarbonImmutable::today()->subDays(1)->toDateString());
        $this->checkinLibre($dani, $this->habil(-1), '09:30', '17:00');

        // Pendiente de activación (sin membresía).
        $this->crearUsuario([
            'name' => 'Emma Pendiente (demo)', 'email' => 'pendiente.demo@nodico.com.mx',
            'telefono' => '9990000005',
        ], RolUsuario::Miembro, EstadoCuenta::Pendiente);

        // Suspendida.
        $fer = $this->crearUsuario([
            'name' => 'Fer Suspendida (demo)', 'email' => 'suspendida.demo@nodico.com.mx',
            'telefono' => '9990000006',
        ]);
        $this->membresias->alta($fer, Plane::where('nombre', 'Nodo Pro')->firstOrFail(),
            $this->recepcion, CarbonImmutable::today()->subDays(15)->toDateString());
        $fer->cambiarEstado(EstadoCuenta::Suspendida);

        // Membresía por vencer esta semana.
        $gabi = $this->crearUsuario([
            'name' => 'Gabi Por Vencer (demo)', 'email' => 'porvencer.demo@nodico.com.mx',
            'empresa' => 'Taller Gabi', 'telefono' => '9990000007',
        ]);
        $susGabi = $this->membresias->alta($gabi, Plane::where('nombre', 'Nodo Pro')->firstOrFail(),
            $this->recepcion, CarbonImmutable::today()->subDays(27)->toDateString());
        // Se acerca el vencimiento a 3 días vista.
        $susGabi->update(['fecha_fin' => CarbonImmutable::today()->addDays(3)->toDateString()]);
    }

    /** Reservas de varios miembros por los ocho espacios, para que la agenda se vea llena. */
    private function llenarLaAgenda(): void
    {
        $beto = User::where('email', 'match.demo@nodico.com.mx')->first();

        $this->reservar($beto, 'sala_juntas', $this->habil(-1), '12:00', '14:00');
        $this->reservar($beto, 'contenido',  $this->habil(1),  '10:00', '12:00');
        $this->reservar($beto, 'fotografia', $this->habil(2),  '11:00', '13:00');
        $this->reservar($beto, 'privado',    $this->habil(-2), '09:00', '11:00');
        $this->reservar($beto, 'privado',    $this->habil(4),  '15:00', '17:00');
    }

    // ── Catálogos ────────────────────────────────────────────────────────────

    private function sembrarCatalogos(): void
    {
        $asesores = [
            ['nombre' => 'Lucía Fernández', 'especialidad' => 'Finanzas y costos', 'email' => 'lucia.demo@nodico.com.mx'],
            ['nombre' => 'Marco Tulio Peña', 'especialidad' => 'Marketing y redes', 'email' => 'marco.demo@nodico.com.mx'],
            ['nombre' => 'Sofía Canul', 'especialidad' => 'Aspectos legales y fiscales', 'email' => 'sofia.demo@nodico.com.mx'],
        ];
        foreach ($asesores as $a) {
            Asesor::updateOrCreate(['email' => $a['email']], $a + ['activo' => true]);
        }

        $emprendedores = [
            ['nombre' => 'Salabtún', 'instagram' => '@salabtun', 'descripcion' => 'Chocolatería artesanal maya.', 'destacado_semana' => true,  'orden' => 1],
            ['nombre' => 'Kinimitas', 'instagram' => '@kinimitas', 'descripcion' => 'Repostería yucateca de temporada.', 'destacado_semana' => false, 'orden' => 2],
            ['nombre' => 'Zentto', 'instagram' => '@zentto', 'descripcion' => 'Diseño de mobiliario sustentable.', 'destacado_semana' => false, 'orden' => 3],
            ['nombre' => 'Saboreli', 'instagram' => '@saboreli', 'descripcion' => 'Conservas y salsas de la milpa.', 'destacado_semana' => false, 'orden' => 4],
        ];
        foreach ($emprendedores as $e) {
            DirectorioEmprendedor::updateOrCreate(['nombre' => $e['nombre']], $e + ['activo' => true]);
        }

        $eventos = [
            ['titulo' => 'Taller: finanzas para tu negocio', 'fecha' => $this->habil(6), 'hora_inicio' => '17:00', 'hora_fin' => '19:00', 'lugar' => 'Yucatán Emprende 1', 'solo_miembros' => false, 'activo' => true],
            ['titulo' => 'Networking Nódico de fin de mes', 'fecha' => $this->habil(9), 'hora_inicio' => '18:00', 'hora_fin' => '20:00', 'lugar' => 'Área de coworking', 'solo_miembros' => true, 'activo' => true],
        ];
        foreach ($eventos as $ev) {
            Evento::updateOrCreate(['titulo' => $ev['titulo']], $ev);
        }

        $hoy = CarbonImmutable::today()->toDateString();
        $enUnMes = CarbonImmutable::today()->addMonth()->toDateString();
        $avisos = [
            ['titulo' => 'Mantenimiento del aire el sábado', 'contenido' => 'El sábado por la mañana habrá mantenimiento del aire acondicionado en el segundo piso.', 'tipo' => 'mantenimiento', 'fecha_inicio' => $hoy, 'fecha_fin' => $enUnMes, 'activo' => true],
            ['titulo' => 'Café nuevo en la barra', 'contenido' => 'Ya tenemos café de altura de un productor de Yucatán. Pásate a probarlo.', 'tipo' => 'general', 'fecha_inicio' => $hoy, 'fecha_fin' => $enUnMes, 'activo' => true],
        ];
        foreach ($avisos as $av) {
            AnuncioCoworking::updateOrCreate(['titulo' => $av['titulo']], $av);
        }
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    /** La fecha del n-ésimo día hábil (L–V) contado desde hoy; negativo = atrás. */
    private function habil(int $offset): string
    {
        $fecha = CarbonImmutable::today();
        $paso = $offset >= 0 ? 1 : -1;
        $restantes = abs($offset);

        while ($restantes > 0) {
            $fecha = $fecha->addDays($paso);
            if ($fecha->isWeekday()) {
                $restantes--;
            }
        }

        // Si hoy cae en fin de semana y offset 0, empújalo al lunes.
        while (! $fecha->isWeekday()) {
            $fecha = $fecha->addDay();
        }

        return $fecha->toDateString();
    }

    private function reservar(User $miembro, string $tipoEspacio, string $fecha, string $ini, string $fin): Reserva
    {
        $espacio = Espacio::where('tipo', $tipoEspacio)->where('disponible', true)->firstOrFail();

        return $this->reservas->crear(
            miembro: $miembro,
            espacio: $espacio,
            fecha: $fecha,
            horaInicio: $ini,
            horaFin: $fin,
            operativo: $this->recepcion,
            autorizaSobrecupo: true,
            motivoSobrecupo: 'Reserva de demostración',
        )['reserva'];
    }

    private function checkinDe(Reserva $reserva, string $fecha, string $entrada, string $salida): void
    {
        Checkin::create([
            'user_id'          => $reserva->user_id,
            'espacio_id'       => $reserva->espacio_id,
            'reserva_id'       => $reserva->id,
            'hora_entrada'     => "{$fecha} {$entrada}:00",
            'hora_salida'      => "{$fecha} {$salida}:00",
            'duracion_minutos' => CarbonImmutable::parse("{$fecha} {$entrada}")
                ->diffInMinutes(CarbonImmutable::parse("{$fecha} {$salida}")),
        ]);
    }

    private function checkinLibre(User $miembro, string $fecha, string $entrada, string $salida): void
    {
        $coworking = Espacio::where('tipo', 'coworking')->first();
        Checkin::create([
            'user_id'          => $miembro->id,
            'espacio_id'       => $coworking?->id,
            'hora_entrada'     => "{$fecha} {$entrada}:00",
            'hora_salida'      => "{$fecha} {$salida}:00",
            'duracion_minutos' => CarbonImmutable::parse("{$fecha} {$entrada}")
                ->diffInMinutes(CarbonImmutable::parse("{$fecha} {$salida}")),
        ]);
    }

    /** Replica el efecto del comando nodico:marcar-no-show sobre una reserva. */
    private function marcarNoShow(Reserva $reserva): void
    {
        $reserva->update(['estatus' => 'No_Show']);

        if ($reserva->suscripcion && $reserva->bolsa()) {
            $this->libro->registrar(
                suscripcion: $reserva->suscripcion,
                bolsa: $reserva->bolsa(),
                cantidad: 0, // Ya consumió al reservar; el no-show no devuelve.
                motivo: MotivoMovimiento::NoShow,
                reserva: $reserva,
                nota: 'No hubo check-in (demo).',
                claveIdempotencia: "no_show:{$reserva->id}",
            );
        }
    }

    /** Replica la cancelación del portal: devuelve solo si toca. */
    private function cancelar(Reserva $reserva, bool $devuelve): void
    {
        if ($devuelve && $reserva->bolsa() && $reserva->suscripcion) {
            $this->libro->registrar(
                suscripcion: $reserva->suscripcion,
                bolsa: $reserva->bolsa(),
                cantidad: -$reserva->duracionEnHoras(),
                motivo: MotivoMovimiento::Cancelacion,
                reserva: $reserva,
                autor: $reserva->user,
            );
        }

        $reserva->update(['estatus' => 'Cancelada']);
        $this->bloques->liberar($reserva);
    }

    private function asesoriaRealizada(\App\Models\Suscripcion $suscripcion, string $tema, string $fecha): void
    {
        $solicitud = SolicitudAsesoria::create([
            'user_id'              => $suscripcion->user_id,
            'suscripcion_id'       => $suscripcion->id,
            'tema'                 => $tema,
            'dia_preferido'        => $fecha,
            'horario_preferido'    => 'Por la mañana',
            'horas'                => 1,
            'estado'               => EstadoAsesoria::Realizada,
            'asesor_nombre'        => 'Lucía Fernández',
            'fecha_confirmada'     => "{$fecha} 10:00",
            'atendida_por_user_id' => $this->recepcion->id,
            'atendida_en'          => "{$fecha} 11:00",
        ]);

        $this->libro->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Asesoria,
            cantidad: 1,
            motivo: MotivoMovimiento::Asesoria,
            solicitud: $solicitud,
            nota: 'Asesoría realizada (demo).',
        );
    }

    private function facturaMensual(User $miembro, \App\Models\Suscripcion $suscripcion, string $fecha): void
    {
        $subtotal = round((float) $suscripcion->precio_pagado / 1.16, 2);
        $iva = round((float) $suscripcion->precio_pagado - $subtotal, 2);

        Factura::create([
            'user_id'        => $miembro->id,
            'suscripcion_id' => $suscripcion->id,
            'folio'          => 'DEMO-' . strtoupper(substr(md5($miembro->id . $fecha), 0, 8)),
            'concepto'       => 'Mensualidad ' . ($suscripcion->plan->nombre ?? 'Nódico'),
            'fecha'          => $fecha,
            'subtotal'       => $subtotal,
            'iva'            => $iva,
            'total'          => (float) $suscripcion->precio_pagado,
            'estatus'        => 'Pagada',
            'metodo_pago'    => 'Tarjeta',
            'fecha_pago'     => $fecha,
        ]);
    }
}
