<?php

namespace App\Servicios\Accesos;

use App\Enums\EstadoCuenta;
use App\Models\User;
use App\Servicios\Membresias\MembresiaDelMiembro;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * La credencial QR del miembro (docs/API-MOVIL.md §6.9; decisión del
 * 22/09/2026: la valida **recepción** desde el panel, no abre el torno).
 *
 * - El código es **opaco**: `NDC1.` más 40 caracteres aleatorios, sin datos
 *   personales dentro. Solo sirve para buscar.
 * - Se valida **en el servidor** al escanearlo. Por eso puede vivir semanas en
 *   el teléfono y funcionar sin conexión: una membresía suspendida o vencida
 *   sale en rojo en recepción aunque el código guardado siga «vigente».
 * - En la base van su hash (para buscarlo) y el propio código cifrado (para
 *   devolverlo a la app cuando lo pida).
 */
class Credenciales
{
    public const PREFIJO = 'NDC1.';

    public function __construct(private readonly MembresiaDelMiembro $membresias)
    {
    }

    /** La credencial vigente, emitiendo una nueva si no hay o caducó. */
    public function vigente(User $usuario): User
    {
        $caducada = ! $usuario->credencial_codigo
            || ! $usuario->credencial_valida_hasta
            || $usuario->credencial_valida_hasta->isPast();

        return $caducada ? $this->emitir($usuario) : $usuario;
    }

    /** Emite un código nuevo; el anterior deja de validar al instante. */
    public function emitir(User $usuario): User
    {
        $codigo = self::PREFIJO . Str::random(40);

        $usuario->forceFill([
            'credencial_codigo'       => $codigo,
            'credencial_hash'         => self::hash($codigo),
            'credencial_emitida_en'   => now(),
            'credencial_valida_hasta' => $this->validaHasta($usuario),
        ])->save();

        return $usuario;
    }

    /**
     * Lo que ve recepción al escanear: quién es y si puede pasar.
     *
     * @return array{encontrada: bool, semaforo?: string, motivo?: string, persona?: array<string, mixed>}
     */
    public function validar(string $codigo): array
    {
        $codigo = trim($codigo);

        if (! str_starts_with($codigo, self::PREFIJO)) {
            return ['encontrada' => false];
        }

        $usuario = User::where('credencial_hash', self::hash($codigo))->first();

        if (! $usuario) {
            return ['encontrada' => false];
        }

        $suscripcion = $this->membresias->vigente($usuario);
        $vigente     = $suscripcion && CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), \App\Models\Reserva::zonaDelCalendario())->gte(\App\Models\Reserva::hoy());

        [$semaforo, $motivo] = match (true) {
            $usuario->estado === EstadoCuenta::Suspendida => ['rojo', 'Cuenta suspendida: ' . $usuario->estado->descripcion()],
            ! $vigente                                  => ['rojo', 'Sin membresía vigente.'],
            $usuario->credencial_valida_hasta?->isPast() ?? true => ['ambar', 'La credencial caducó; que abra la app con datos para renovarla.'],
            ! $usuario->face_id_ok                      => ['ambar', 'Membresía vigente, pero falta registrar su Face ID.'],
            default                                     => ['verde', 'Membresía vigente.'],
        };

        return [
            'encontrada' => true,
            'semaforo'   => $semaforo,
            'motivo'     => $motivo,
            'persona'    => [
                'id'         => $usuario->id,
                'nombre'     => $usuario->name,
                'email'      => $usuario->email,
                'avatar'     => $usuario->avatar,
                'plan'       => $suscripcion?->plan?->nombre,
                'vence'      => $suscripcion?->fecha_fin?->toDateString(),
                'rol'        => $this->membresias->rol($usuario, $suscripcion),
                'face_id_ok' => (bool) $usuario->face_id_ok,
            ],
        ];
    }

    public static function hash(string $codigo): string
    {
        return hash('sha256', $codigo);
    }

    /** Hasta el fin de la membresía, con tope de `credencial_dias`. */
    private function validaHasta(User $usuario): CarbonImmutable
    {
        $tope        = CarbonImmutable::now()->addDays((int) config('nodico.app_movil.credencial_dias', 35));
        $suscripcion = $this->membresias->vigente($usuario);

        if (! $suscripcion) {
            // Sin membresía también hay credencial (identifica a la persona),
            // pero corta: recepción la verá en rojo de todos modos.
            return CarbonImmutable::now()->addDays(7);
        }

        $fin = CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), \App\Models\Reserva::zonaDelCalendario())->endOfDay();

        return $fin->isPast() ? CarbonImmutable::now()->addDays(7) : $fin->min($tope);
    }
}
