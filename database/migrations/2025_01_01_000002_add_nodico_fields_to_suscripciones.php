<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->decimal('horas_sala_usadas', 6, 2)->default(0)->after('auto_renovar');
            $table->decimal('horas_contenido_usadas', 6, 2)->default(0)->after('horas_sala_usadas');
            $table->integer('dias_usados')->default(0)->after('horas_contenido_usadas');
            $table->unsignedBigInteger('companion_user_id')->nullable()->after('dias_usados');
            $table->boolean('companion_face_id_ok')->default(false)->after('companion_user_id');
            $table->foreign('companion_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropForeign(['companion_user_id']);
            $table->dropColumn(['horas_sala_usadas', 'horas_contenido_usadas', 'dias_usados', 'companion_user_id', 'companion_face_id_ok']);
        });
    }
};
