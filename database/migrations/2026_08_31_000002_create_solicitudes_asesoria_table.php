<?php

use App\Enums\EstadoAsesoria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.2 — solicitudes de asesoría IYEM.
 *
 * Nodo Pro incluye 4 h al mes (1 h al día) y el modelo no las contemplaba en
 * absoluto: ni cupo en `planes`, ni bolsa en `suscripciones`, ni sitio donde
 * pedirlas. Las dos primeras entraron en la Fase 0; esta es la tercera.
 *
 * El asesor se guarda de **dos formas a la vez** a propósito. Hoy Nódico no
 * tiene a los asesores del IYEM dados de alta como usuarios y recepción los
 * escribe a mano, así que `asesor_nombre` es lo que se usa. El día que los den
 * de alta, `asesor_user_id` ya está, y no hace falta migrar nada ni volver a
 * tocar la tabla. Cuál de los dos manda es una decisión pendiente de Nódico.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_asesoria', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('user_id')->constrained()->cascadeOnDelete();
            $tabla->foreignId('suscripcion_id')->constrained('suscripciones')->cascadeOnDelete();

            $tabla->text('tema');

            // Lo que pide el miembro.
            $tabla->date('dia_preferido');
            $tabla->string('horario_preferido');
            $tabla->decimal('horas', 5, 2)->default(1);

            $tabla->enum('estado', EstadoAsesoria::valores())->default(EstadoAsesoria::Solicitada->value);

            // Lo que decide el operativo.
            $tabla->string('asesor_nombre')->nullable();
            $tabla->foreignId('asesor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->dateTime('fecha_confirmada')->nullable();
            $tabla->text('notas_operativo')->nullable();

            // Quién la atendió y cuándo, para la bitácora y la carga por asesor.
            $tabla->foreignId('atendida_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('atendida_en')->nullable();

            $tabla->timestamps();

            // La bandeja de pendientes del operativo (Fase 3.7).
            $tabla->index(['estado', 'dia_preferido']);

            // El historial del miembro.
            $tabla->index(['user_id', 'created_at']);
        });

        // El movimiento del libro apunta a la solicitud que lo originó, igual
        // que apunta a la reserva. Se añade aquí y no en la migración del libro
        // porque la tabla destino no existía todavía.
        Schema::table('movimientos_horas', function (Blueprint $tabla) {
            $tabla->foreignId('solicitud_asesoria_id')
                ->nullable()
                ->after('reserva_id')
                ->constrained('solicitudes_asesoria')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_horas', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('solicitud_asesoria_id');
        });

        Schema::dropIfExists('solicitudes_asesoria');
    }
};
