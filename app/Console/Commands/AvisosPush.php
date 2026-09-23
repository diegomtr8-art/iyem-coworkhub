<?php

namespace App\Console\Commands;

use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Notifications\AvisoDeLaApp;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Avisos push programados de la app.
 *
 * - **Recordatorio de reserva**, una hora antes. Corre cada 15 minutos y avisa
 *   de las que empiezan dentro de la siguiente ventana.
 * - **Membresía por vencer**, a 7 y a 1 día, una vez al día.
 *
 * Cada aviso se marca en caché para no repetirse aunque el comando corra de
 * más. Solo llega a quien tiene la app con sesión y registró sus avisos.
 */
class AvisosPush extends Command
{
    protected $signature = 'nodico:avisos-push';

    protected $description = 'Manda los avisos push programados de la app (recordatorios de reserva y membresía por vencer).';

    public function handle(): int
    {
        $enviados = $this->recordatoriosDeReserva() + $this->membresiasPorVencer();

        $this->info("Avisos enviados: {$enviados}.");

        return self::SUCCESS;
    }

    private function recordatoriosDeReserva(): int
    {
        $ahora  = CarbonImmutable::now();
        $cuenta = 0;

        Reserva::with('user', 'espacio')
            ->confirmadas()
            ->whereDate('fecha', '>=', $ahora->toDateString())
            ->whereDate('fecha', '<=', $ahora->addDay()->toDateString())
            ->whereHas('user.dispositivosPush')
            ->get()
            ->filter(function (Reserva $r) use ($ahora) {
                $inicio = $r->inicioEnCalendario();

                return $inicio->gt($ahora->addMinutes(45)) && $inicio->lte($ahora->addMinutes(75));
            })
            ->each(function (Reserva $r) use (&$cuenta) {
                if (! Cache::add("push:reserva:{$r->id}", true, now()->addDays(2))) {
                    return;
                }

                $r->user->notify(new AvisoDeLaApp(
                    'reservas',
                    'Tu reserva empieza en una hora',
                    ($r->espacio?->nombre ?? 'Tu espacio') . ' · ' . substr((string) $r->hora_inicio, 0, 5) . ' a ' . substr((string) $r->hora_fin, 0, 5) . '.',
                    ['pantalla' => 'reserva', 'id' => $r->id],
                ));

                $cuenta++;
            });

        return $cuenta;
    }

    private function membresiasPorVencer(): int
    {
        $cuenta = 0;

        foreach ([7, 1] as $dias) {
            $fecha = CarbonImmutable::today()->addDays($dias)->toDateString();

            Suscripcion::with('user', 'plan')
                ->where('estatus', 'Activa')
                ->whereDate('fecha_fin', $fecha)
                ->whereHas('user.dispositivosPush')
                ->get()
                ->each(function (Suscripcion $s) use ($dias, &$cuenta) {
                    if (! Cache::add("push:vence:{$s->id}:{$dias}", true, now()->addDays(3))) {
                        return;
                    }

                    $s->user->notify(new AvisoDeLaApp(
                        'membresia',
                        $dias === 1 ? 'Tu membresía vence mañana' : 'Tu membresía vence en una semana',
                        'Renueva tu ' . ($s->plan?->nombre ?? 'membresía') . ' desde la app para no perder tus horas.',
                        ['pantalla' => 'membresia'],
                    ));

                    $cuenta++;
                });
        }

        return $cuenta;
    }
}
