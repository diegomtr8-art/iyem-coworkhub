<?php

namespace App\Console\Commands;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Reservas\RegistroDeBloques;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Fase 4 — prueba de concurrencia **de verdad** (BUG-03).
 *
 * Las pruebas de PHPUnit corren sobre SQLite en memoria y en un solo proceso:
 * comprueban que la lógica rechaza el traslape, pero **no pueden demostrar que
 * la carrera está cerrada**, porque nunca hay dos escrituras a la vez.
 *
 * Esto lanza N procesos reales que intentan reservar el mismo hueco en el mismo
 * instante contra MySQL. Es la única forma de comprobar que el bloqueo
 * pesimista y el índice único de `bloques_reserva` hacen su trabajo.
 *
 * Se corre a mano, no en la suite: necesita MySQL, tarda segundos y escribe en
 * la base. `php artisan nodico:probar-concurrencia`
 */
class ProbarConcurrencia extends Command
{
    protected $signature = 'nodico:probar-concurrencia
                            {--intentos=8 : Cuántos procesos compiten por el mismo hueco.}
                            {--ingenuo : Reproduce el codigo anterior al BUG-03, para comprobar que esta prueba de verdad detecta la carrera.}
                            {--hijo= : Uso interno: id del proceso hijo.}
                            {--espacio= : Uso interno.}
                            {--fecha= : Uso interno.}
                            {--usuarios= : Uso interno: ids separados por coma.}';

    protected $description = 'Lanza procesos en paralelo contra el mismo hueco y comprueba que solo uno gana.';

    public function handle(): int
    {
        return $this->option('hijo') !== null
            ? $this->correrComoHijo()
            : $this->correrComoPadre();
    }

    private function correrComoPadre(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Esta prueba necesita MySQL: en SQLite no hay concurrencia que probar.');

            return self::FAILURE;
        }

        $intentos = max(2, (int) $this->option('intentos'));

        $this->info("Preparando el escenario: {$intentos} procesos por un solo hueco.");

        [$espacio, $fecha, $usuarios] = $this->prepararEscenario($intentos);

        $this->line("  espacio #{$espacio->id} · {$fecha} · 10:00–11:00");
        $this->line('  usuarios: ' . implode(', ', $usuarios));
        $this->newLine();

        // Todos arrancan con una barrera de tiempo compartida: se les dice a qué
        // milisegundo exacto deben lanzar el INSERT. Sin eso, el arranque de PHP
        // (~200 ms) los escalona y no compiten de verdad.
        $salida = (int) (microtime(true) * 1000) + 2500;

        $this->info('Lanzando…');

        $procesos = [];

        foreach ($usuarios as $i => $userId) {
            $proceso = new Process([
                PHP_BINARY, 'artisan', 'nodico:probar-concurrencia',
                '--hijo=' . $i,
                '--espacio=' . $espacio->id,
                '--fecha=' . $fecha,
                '--usuarios=' . $userId,
                '--intentos=' . $salida,
                ...($this->option('ingenuo') ? ['--ingenuo'] : []),
            ], base_path());

            $proceso->setTimeout(60);
            $proceso->start();
            $procesos[$i] = $proceso;
        }

        $resultados = [];

        foreach ($procesos as $i => $proceso) {
            $proceso->wait();
            $resultados[$i] = trim($proceso->getOutput() . $proceso->getErrorOutput());
        }

        $this->newLine();

        foreach ($resultados as $i => $texto) {
            $gano = str_contains($texto, 'GANO');
            $this->line(sprintf('  proceso %d: %s', $i, $gano ? '<fg=green>GANÓ</>' : '<fg=yellow>rechazado</>'));

            if (! $gano && ! str_contains($texto, 'RECHAZADO')) {
                $this->warn('    salida inesperada: ' . mb_substr($texto, 0, 200));
            }
        }

        // El veredicto: exactamente una reserva confirmada en ese hueco.
        $confirmadas = Reserva::where('espacio_id', $espacio->id)
            ->whereDate('fecha', $fecha)
            ->where('estatus', 'Confirmada')
            ->count();

        $bloques = DB::table('bloques_reserva')
            ->where('espacio_id', $espacio->id)
            ->where('fecha', $fecha)
            ->count();

        $this->newLine();
        $this->line("  reservas confirmadas en el hueco: {$confirmadas}");
        $this->line("  bloques ocupados: {$bloques} (esperados: 2, de 10:00 y 10:30)");

        $this->limpiar($espacio, $fecha, $usuarios);

        $this->newLine();

        if ($this->option('ingenuo')) {
            // Aqui el exito es lo contrario: si el codigo viejo NO se cuela,
            // la prueba no tiene poder de deteccion y no demuestra nada.
            if ($confirmadas > 1) {
                $this->info("✔ Poder de deteccion confirmado: el codigo anterior dejo pasar "
                    . "{$confirmadas} reservas sobre el mismo hueco.");

                return self::SUCCESS;
            }

            $this->error('✘ Ni siquiera el codigo ingenuo se colo: sube --intentos o revisa '
                . 'que los procesos esten arrancando a la vez. Esta prueba no esta probando nada.');

            return self::FAILURE;
        }

        if ($confirmadas === 1 && $bloques === 2) {
            $this->info("✔ La carrera está cerrada: {$intentos} procesos compitieron y solo uno reservó.");

            return self::SUCCESS;
        }

        $this->error("✘ BUG-03 DE VUELTA: quedaron {$confirmadas} reservas y {$bloques} bloques.");

        return self::FAILURE;
    }

    /** Un intento. Se sincroniza con los demás y escribe GANO o RECHAZADO. */
    private function correrComoHijo(): int
    {
        $espacio = Espacio::findOrFail((int) $this->option('espacio'));
        $fecha   = (string) $this->option('fecha');
        $userId  = (int) $this->option('usuarios');
        $salida  = (int) $this->option('intentos');

        // Barrera: todos esperan al mismo milisegundo.
        $espera = $salida - (int) (microtime(true) * 1000);

        if ($espera > 0) {
            usleep($espera * 1000);
        }

        $suscripcion = Suscripcion::with('plan')
            ->where('user_id', $userId)
            ->where('estatus', 'Activa')
            ->first();

        // Modo ingenuo: comprobar FUERA de la transaccion y sin ocupar bloques,
        // que es exactamente lo que hacia el codigo anterior. Sirve para
        // demostrar que esta prueba tiene poder de deteccion: si aqui no se
        // cuelan duplicados, la prueba no vale para nada.
        if ($this->option('ingenuo')) {
            $ocupado = Reserva::where('espacio_id', $espacio->id)
                ->whereDate('fecha', $fecha)
                ->where('estatus', 'Confirmada')
                ->where('hora_inicio', '<', '11:00')
                ->where('hora_fin', '>', '10:00')
                ->exists();

            if ($ocupado) {
                $this->line('RECHAZADO');

                return self::SUCCESS;
            }

            Reserva::create([
                'user_id'        => $userId,
                'espacio_id'     => $espacio->id,
                'suscripcion_id' => $suscripcion?->id,
                'fecha'          => $fecha,
                'hora_inicio'    => '10:00',
                'hora_fin'       => '11:00',
                'estatus'        => 'Confirmada',
                'precio_total'   => 0,
            ]);

            $this->line('GANO');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($espacio, $fecha, $userId, $suscripcion) {
                Reserva::where('espacio_id', $espacio->id)
                    ->whereDate('fecha', $fecha)
                    ->lockForUpdate()
                    ->get();

                $ocupado = Reserva::where('espacio_id', $espacio->id)
                    ->whereDate('fecha', $fecha)
                    ->where('estatus', 'Confirmada')
                    ->where('hora_inicio', '<', '11:00')
                    ->where('hora_fin', '>', '10:00')
                    ->exists();

                if ($ocupado) {
                    throw new \RuntimeException('ocupado');
                }

                $reserva = Reserva::create([
                    'user_id'        => $userId,
                    'espacio_id'     => $espacio->id,
                    'suscripcion_id' => $suscripcion?->id,
                    'fecha'          => $fecha,
                    'hora_inicio'    => '10:00',
                    'hora_fin'       => '11:00',
                    'estatus'        => 'Confirmada',
                    'precio_total'   => 0,
                ]);

                app(RegistroDeBloques::class)->ocupar($reserva);

                if ($suscripcion) {
                    app(LibroDeHoras::class)->registrar(
                        suscripcion: $suscripcion,
                        bolsa: BolsaDeHoras::Sala,
                        cantidad: 1,
                        motivo: MotivoMovimiento::Reserva,
                        reserva: $reserva,
                    );
                }
            });

            $this->line('GANO');
        } catch (QueryException|\RuntimeException $e) {
            // Tanto el traslape detectado como la violación del índice único son
            // el resultado correcto: la carrera se cerró.
            $this->line('RECHAZADO');
        }

        return self::SUCCESS;
    }

    /** @return array{0: Espacio, 1: string, 2: array<int, int>} */
    private function prepararEscenario(int $intentos): array
    {
        $plan = Plane::firstOrCreate(
            ['nombre' => 'Plan de prueba de concurrencia'],
            ['tipo' => 'mes', 'precio' => 0, 'horas_sala_mes' => 50, 'activo' => false, 'color' => '#000000'],
        );

        $espacio = Espacio::firstOrCreate(
            ['nombre' => 'Sala de prueba de concurrencia'],
            ['tipo' => 'sala_juntas', 'capacidad' => 4, 'disponible' => false, 'piso' => 1],
        );

        // Una fecha muy lejana para no chocar con nada real.
        $fecha = CarbonImmutable::today()->addYears(3)->next(CarbonImmutable::MONDAY)->toDateString();

        $usuarios = [];

        for ($i = 0; $i < $intentos; $i++) {
            $user = User::firstOrCreate(
                ['email' => "concurrencia{$i}@prueba.local"],
                ['name' => "Prueba concurrencia {$i}", 'password' => bcrypt(bin2hex(random_bytes(8)))],
            );

            Suscripcion::firstOrCreate(
                ['user_id' => $user->id, 'plan_id' => $plan->id],
                [
                    'fecha_inicio'  => CarbonImmutable::today()->toDateString(),
                    'fecha_fin'     => CarbonImmutable::today()->addYears(5)->toDateString(),
                    'estatus'       => 'Activa',
                    'precio_pagado' => 0,
                ],
            );

            $usuarios[] = $user->id;
        }

        $this->limpiar($espacio, $fecha, $usuarios, false);

        return [$espacio, $fecha, $usuarios];
    }

    private function limpiar(Espacio $espacio, string $fecha, array $usuarios, bool $avisar = true): void
    {
        $reservas = Reserva::where('espacio_id', $espacio->id)->whereDate('fecha', $fecha)->pluck('id');

        DB::table('bloques_reserva')->whereIn('reserva_id', $reservas)->delete();
        DB::table('movimientos_horas')->whereIn('reserva_id', $reservas)->delete();
        Reserva::whereIn('id', $reservas)->delete();

        if ($avisar && $reservas->isNotEmpty()) {
            $this->line('  (escenario limpiado)');
        }
    }
}
