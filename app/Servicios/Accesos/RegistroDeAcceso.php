<?php

namespace App\Servicios\Accesos;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entradas y salidas del espacio.
 *
 * Existe por dos razones. La primera es el **BUG-05**: `suscripciones.dias_usados`
 * estaba en la tabla, `diasRestantesMes()` lo leía y **nada en el sistema lo
 * incrementaba**, así que el control de días de Day-Pass (1 día) y Nódico Flex
 * (4 días) no existía: esos miembros tenían acceso indefinido.
 *
 * La segunda es que la lógica de cerrar accesos abiertos estaba duplicada
 * palabra por palabra en `Portal\CheckinController` y en `CheckinAdminController`.
 * Dos copias de una regla es como empezó el BUG-02.
 *
 * El día se consume **una sola vez por día natural**, no por reserva ni por
 * entrada: salir a comer y volver no gasta un segundo día.
 */
class RegistroDeAcceso
{
    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    /**
     * Registra la entrada de un miembro y consume día si su plan va por días.
     *
     * @throws ValidationException si no tiene membresía activa o se quedó sin días.
     */
    public function entrada(User $usuario, ?Espacio $espacio = null): Checkin
    {
        return DB::transaction(function () use ($usuario, $espacio) {
            $suscripcion = $usuario->suscripciones()
                ->with('plan')
                ->where('estatus', 'Activa')
                ->lockForUpdate()
                ->latest('fecha_inicio')
                ->first();

            if (! $suscripcion) {
                throw ValidationException::withMessages([
                    'general' => 'Necesitas una membresía activa para entrar.',
                ]);
            }

            $hoy = CarbonImmutable::today();

            if ($hoy->gt(CarbonImmutable::parse($suscripcion->fecha_fin))) {
                throw ValidationException::withMessages([
                    'general' => 'Tu membresía venció el '
                        . CarbonImmutable::parse($suscripcion->fecha_fin)->translatedFormat('j \d\e F') . '.',
                ]);
            }

            $consumeDia = $this->debeConsumirDia($suscripcion, $hoy);

            if ($consumeDia) {
                $restantes = $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Dias);

                if ($restantes !== null && $restantes < 1) {
                    throw ValidationException::withMessages([
                        'general' => 'Ya usaste los ' . (int) BolsaDeHoras::Dias->cupo($suscripcion->plan)
                            . ' días de tu membresía ' . $suscripcion->plan?->nombre . '.',
                    ]);
                }
            }

            $this->cerrarAccesosAbiertos($usuario);

            $espacio ??= Espacio::where('tipo', \App\Enums\TipoEspacio::Coworking->value)
                ->where('disponible', true)
                ->first();

            $acceso = Checkin::create([
                'user_id'      => $usuario->id,
                'espacio_id'   => $espacio?->id,
                'reserva_id'   => $this->reservaDelMomento($usuario, $espacio)?->id,
                'hora_entrada' => now(),
            ]);

            if ($consumeDia) {
                // Por el libro, no por `increment`: el día consumido tiene que
                // poder explicarse igual que una hora de sala. La clave hace la
                // operación idempotente incluso si dos peticiones entran a la vez.
                $this->libro->registrar(
                    suscripcion: $suscripcion,
                    bolsa: BolsaDeHoras::Dias,
                    cantidad: 1,
                    motivo: MotivoMovimiento::Acceso,
                    autor: $usuario,
                    nota: 'Entrada del ' . $hoy->translatedFormat('j \d\e F \d\e Y') . '.',
                    claveIdempotencia: "acceso:{$suscripcion->id}:{$hoy->toDateString()}",
                );
            }

            return $acceso;
        });
    }

    /** Cierra el acceso abierto del miembro. Devuelve `null` si no tenía ninguno. */
    public function salida(User $usuario): ?Checkin
    {
        $acceso = $usuario->checkins()->whereNull('hora_salida')->latest('hora_entrada')->first();

        if (! $acceso) {
            return null;
        }

        $this->cerrar($acceso);

        return $acceso->refresh();
    }

    /**
     * Si esta entrada consume un día de la bolsa.
     *
     * No consume cuando el plan es ilimitado (Nodo Pro, Nodo Match) ni cuando ya
     * hubo una entrada hoy con la misma suscripción.
     */
    private function debeConsumirDia(Suscripcion $suscripcion, CarbonImmutable $dia): bool
    {
        if ($suscripcion->plan?->esIlimitado() ?? true) {
            return false;
        }

        $yaEntroHoy = Checkin::where('user_id', $suscripcion->user_id)
            ->whereDate('hora_entrada', $dia->toDateString())
            ->exists();

        return ! $yaEntroHoy;
    }

    private function cerrarAccesosAbiertos(User $usuario): void
    {
        $usuario->checkins()
            ->whereNull('hora_salida')
            ->get()
            ->each(fn (Checkin $acceso) => $this->cerrar($acceso));
    }

    /**
     * Cierra un acceso. El sentido de la resta importa: de entrada hacia ahora,
     * que es positivo. Invertirlo es el BUG-01 en otra tabla.
     */
    private function cerrar(Checkin $acceso): void
    {
        $minutos = (int) CarbonImmutable::parse($acceso->hora_entrada)->diffInMinutes(now());

        $acceso->update([
            'hora_salida'      => now(),
            'duracion_minutos' => max(0, $minutos),
        ]);
    }

    /** Reserva confirmada que esté corriendo ahora, para enlazarla con el acceso. */
    private function reservaDelMomento(User $usuario, ?Espacio $espacio): ?Reserva
    {
        $ahora = now();

        return Reserva::where('user_id', $usuario->id)
            ->whereDate('fecha', $ahora->toDateString())
            ->confirmadas()
            ->when($espacio, fn ($q) => $q->where('espacio_id', $espacio->id))
            ->where('hora_inicio', '<=', $ahora->format('H:i:s'))
            ->where('hora_fin', '>=', $ahora->format('H:i:s'))
            ->first();
    }
}
