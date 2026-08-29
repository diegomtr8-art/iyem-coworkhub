<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.4 — lo que impide reservar, más allá del cupo.
 *
 * Nada de esto estaba modelado, y sin ello las reservas no significan gran
 * cosa: hoy se puede apartar una sala a las tres de la mañana de un domingo, o
 * el 25 de diciembre, o mientras la están pintando.
 *
 * Tres piezas:
 *
 * - **Horario propio del espacio.** El general vive en `config/nodico.php`
 *   (L-V 9:00–19:00); estas columnas solo existen para el espacio que se salga
 *   de él. `null` = sigue el horario general, que es el caso de casi todos.
 * - **Bloqueos.** Mantenimiento o evento privado. Compiten con las reservas
 *   igual que cualquier otra: por eso ocupan bloques en `bloques_reserva`, la
 *   misma tabla, y el índice único los enfrenta sin código extra.
 * - **Días festivos.** Cierres del calendario, para todos los espacios.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('espacios', function (Blueprint $tabla) {
            $tabla->time('hora_apertura')->nullable()->after('piso');
            $tabla->time('hora_cierre')->nullable()->after('hora_apertura');

            // Días de la semana en formato Carbon (1 = lunes). `null` = los generales.
            $tabla->json('dias_operacion')->nullable()->after('hora_cierre');
        });

        Schema::create('bloqueos_espacio', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('espacio_id')->constrained('espacios')->cascadeOnDelete();
            $tabla->date('fecha');
            $tabla->time('hora_inicio');
            $tabla->time('hora_fin');

            $tabla->string('motivo');
            $tabla->text('notas')->nullable();

            $tabla->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();

            $tabla->index(['espacio_id', 'fecha']);
        });

        // Un bloqueo ocupa bloques igual que una reserva. Como `bloques_reserva`
        // exigía `reserva_id`, la columna pasa a ser nula y entra su gemela.
        // Exactamente una de las dos tiene valor, y el índice único sigue
        // enfrentando reservas contra bloqueos sin saber cuál es cuál.
        Schema::table('bloques_reserva', function (Blueprint $tabla) {
            $tabla->foreignId('bloqueo_id')->nullable()->after('reserva_id')
                ->constrained('bloqueos_espacio')->cascadeOnDelete();
        });

        Schema::table('bloques_reserva', function (Blueprint $tabla) {
            $tabla->foreignId('reserva_id')->nullable()->change();
        });

        Schema::create('dias_festivos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->date('fecha')->unique();
            $tabla->string('nombre');

            // Un cierre puede ser parcial: 24 de diciembre hasta las 14:00.
            $tabla->boolean('cerrado_todo_el_dia')->default(true);
            $tabla->time('hora_apertura')->nullable();
            $tabla->time('hora_cierre')->nullable();

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_festivos');

        Schema::table('bloques_reserva', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('bloqueo_id');
        });

        Schema::dropIfExists('bloqueos_espacio');

        Schema::table('espacios', function (Blueprint $tabla) {
            $tabla->dropColumn(['hora_apertura', 'hora_cierre', 'dias_operacion']);
        });
    }
};
