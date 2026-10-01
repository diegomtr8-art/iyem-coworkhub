<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Mantiene utilizables las cuentas de demostración (`*.demo@nodico.com.mx`).
 *
 * `DemoSeeder` siembra fechas relativas al día en que corre —membresías que
 * empezaron hace 20 días, una que vence en 3…—, pero el despliegue solo
 * vuelve a sembrar `NodicoWebSeeder`. Sembrado el 31 de agosto, para el 29 de
 * septiembre todas las membresías demo habían vencido y el calendario de
 * reservas salía vacío en las pruebas de servicio social.
 *
 * Esto vuelve a sembrar el demo **solo cuando ya envejeció** (`--dias`,
 * contado desde que se creó `admin.demo`), para no borrar a diario lo que la
 * gente está probando. `--forzar` lo rehace ya. `DemoSeeder` borra y rehace
 * solo las cuentas `*.demo`; lo demás no se toca.
 *
 * Nunca en producción.
 */
class DemoAlDia extends Command
{
    protected $signature = 'nodico:demo-al-dia
        {--dias=7 : Resiembra si el demo tiene al menos estos días}
        {--forzar : Resiembra aunque sea reciente}';

    protected $description = 'Vuelve a sembrar las cuentas de demostración cuando sus fechas envejecieron.';

    public function handle(): int
    {
        // Lista blanca y no «todo menos production»: un APP_ENV mal escrito en
        // el servidor real (`prod`, `produccion`) no puede bastar para que se
        // borren y rehagan cuentas.
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->error('nodico:demo-al-dia solo corre en local, staging o pruebas: borra y rehace las cuentas de demostración.');

            return self::FAILURE;
        }

        $sembradoEn = User::where('email', 'admin.demo@nodico.com.mx')->value('created_at');
        $dias = max(1, (int) $this->option('dias'));

        if ($sembradoEn && ! $this->option('forzar')) {
            $edad = (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($sembradoEn)->startOfDay(), absolute: true);

            if ($edad < $dias) {
                $this->info("El demo está al día (sembrado hace {$edad} día(s); se resiembra a los {$dias}).");

                return self::SUCCESS;
            }

            $this->line("El demo tiene {$edad} día(s): se vuelve a sembrar con fechas de hoy.");
        } elseif (! $sembradoEn) {
            $this->line('No hay cuentas de demostración: se siembran.');
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
