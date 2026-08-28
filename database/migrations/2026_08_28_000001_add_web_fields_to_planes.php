<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->string('stripe_url')->nullable()->after('activo');
            $table->json('beneficios')->nullable()->after('stripe_url');
            $table->text('descripcion_corta')->nullable()->after('beneficios');
            $table->text('descripcion_larga')->nullable()->after('descripcion_corta');
            $table->string('periodo_label')->nullable()->after('descripcion_larga');
            $table->string('cta_label')->nullable()->after('periodo_label');
            $table->string('imagen')->nullable()->after('cta_label');
            $table->integer('orden')->default(0)->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_url', 'beneficios', 'descripcion_corta', 'descripcion_larga',
                'periodo_label', 'cta_label', 'imagen', 'orden',
            ]);
        });
    }
};
