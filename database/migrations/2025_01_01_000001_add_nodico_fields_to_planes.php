<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->string('subtitulo')->nullable()->after('nombre');
            $table->integer('dias_cowork_mes')->nullable()->after('acceso_24h'); // null=ilimitado
            $table->integer('horas_sala_mes')->nullable()->after('dias_cowork_mes'); // null=sin acceso
            $table->integer('horas_contenido_mes')->nullable()->after('horas_sala_mes');
            $table->integer('max_horas_sala_dia')->nullable()->after('horas_contenido_mes');
            $table->integer('personas')->default(1)->after('max_horas_sala_dia');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn(['subtitulo', 'dias_cowork_mes', 'horas_sala_mes', 'horas_contenido_mes', 'max_horas_sala_dia', 'personas']);
        });
    }
};
