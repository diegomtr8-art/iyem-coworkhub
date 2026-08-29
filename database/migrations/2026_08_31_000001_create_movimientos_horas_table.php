<?php

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.1 — el libro de movimientos de horas.
 *
 * Hasta ahora el consumo vivía en tres contadores sueltos
 * (`horas_sala_usadas`, `horas_contenido_usadas`, `dias_usados`). Un contador
 * no se puede auditar, no se puede corregir con constancia y no explica de
 * dónde salió el saldo: el BUG-01 estuvo meses invisible justo por eso.
 *
 * A partir de aquí **el saldo se calcula sumando movimientos**. Los contadores
 * de `suscripciones` se quedan como caché, reconstruible con
 * `php artisan nodico:reconstruir-saldos`.
 *
 * ## Signo
 *
 * `cantidad` es **positiva cuando consume** la bolsa y negativa cuando la
 * devuelve. Se eligió así para que el consumo del ciclo sea literalmente
 * `SUM(cantidad)` y coincida con lo que ya guardaban los contadores, sin
 * invertir nada al migrar. El saldo restante es `cupo del plan − SUM(cantidad)`.
 *
 * ## Idempotencia
 *
 * `clave_idempotencia` lleva un índice único y es `null` en los movimientos
 * normales —MySQL admite tantos `null` como quieras en un índice único—. Las
 * operaciones que no pueden ocurrir dos veces la rellenan: el reinicio de
 * ciclo usa `reinicio:{suscripcion}:{bolsa}:{fecha}`, de modo que correr el
 * comando dos veces el mismo día choca contra el índice en vez de duplicar el
 * ciclo. Lo mismo hace el marcado de no-show.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('movimientos_horas', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();
            $tabla->enum('bolsa', BolsaDeHoras::valores());

            // Con signo: positivo consume, negativo devuelve.
            $tabla->decimal('cantidad', 8, 2);

            $tabla->enum('motivo', MotivoMovimiento::valores());

            // A qué ciclo de aniversario pertenece el movimiento. Es lo que
            // permite que las horas no se acumulen: al cambiar de ciclo, la
            // suma parte de cero sin borrar nada del histórico.
            $tabla->date('ciclo_inicio');

            $tabla->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();

            // Quién lo originó. `null` = lo escribió el sistema (un comando
            // programado), no una persona.
            $tabla->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->text('nota')->nullable();

            $tabla->string('clave_idempotencia')->nullable()->unique();

            $tabla->timestamps();

            // La consulta de saldo: suma por suscripción, bolsa y ciclo.
            $tabla->index(['suscripcion_id', 'bolsa', 'ciclo_inicio'], 'movimientos_saldo');

            // El historial que ve el miembro y la ficha del operativo.
            $tabla->index(['suscripcion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_horas');
    }
};
