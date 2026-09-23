<?php

use App\Enums\AccionOperativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * App móvil (ver docs/API-MOVIL.md).
 *
 * - `personal_access_tokens`: de qué teléfono es cada token, para enseñarlo en
 *   «Mi seguridad» y para revocar el anterior al volver a entrar desde el mismo.
 * - `dispositivos_push`: el token de notificaciones de Expo, atado al token de
 *   Sanctum para que cerrar sesión deje de mandar avisos a ese teléfono.
 * - `users.credencial_*`: la credencial QR. El código se guarda cifrado (hay
 *   que poder devolverlo a la app) y además su hash, que es por lo que se busca
 *   al escanearlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('dispositivo_id', 64)->nullable()->after('name');
            $table->string('plataforma', 10)->nullable()->after('dispositivo_id');
            $table->index(['tokenable_id', 'dispositivo_id']);
        });

        Schema::create('dispositivos_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->constrained()->cascadeOnDelete();
            $table->string('expo_push_token', 255)->unique();
            $table->string('plataforma', 10)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('credencial_codigo')->nullable();
            $table->string('credencial_hash', 64)->nullable()->unique();
            $table->timestamp('credencial_emitida_en')->nullable();
            $table->timestamp('credencial_valida_hasta')->nullable();
        });

        if (DB::getDriverName() === 'mysql') {
            $vals = collect(AccionOperativa::valores())
                ->map(fn ($v) => "'" . $v . "'")->implode(',');

            DB::statement("ALTER TABLE bitacora_operacion MODIFY accion ENUM($vals) NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['credencial_hash']);
            $table->dropColumn(['credencial_codigo', 'credencial_hash', 'credencial_emitida_en', 'credencial_valida_hasta']);
        });

        Schema::dropIfExists('dispositivos_push');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tokenable_id', 'dispositivo_id']);
            $table->dropColumn(['dispositivo_id', 'plataforma']);
        });
    }
};
