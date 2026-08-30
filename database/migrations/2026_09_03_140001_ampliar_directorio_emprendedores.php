<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.H — el directorio de emprendedores pasa a ser un catálogo administrable
 * que alimenta a la vez el directorio de `/actividades` y el «emprendedor de la
 * semana» rotativo.
 *
 * Campos de ficha (H.1) y de rotación (H.3):
 *  - `giro`, `municipio`, `sitio_web`, `egresado_iyem` completan la ficha.
 *  - `elegible_destacado` decide quién entra en la rotación del destacado.
 *  - `destacado_ultima_vez` es lo que hace la rotación **justa**: el próximo es
 *    el que lleva más tiempo sin salir (o nunca ha salido).
 *  - `fijado_hasta` permite clavar uno a mano una semana concreta sin romper el
 *    ciclo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('directorio_emprendedores', function (Blueprint $table) {
            $table->string('giro')->nullable()->after('descripcion');
            $table->string('municipio')->nullable()->after('giro');
            $table->string('sitio_web')->nullable()->after('url_destino');
            $table->boolean('egresado_iyem')->default(false)->after('sitio_web');
            $table->boolean('elegible_destacado')->default(true)->after('destacado_semana');
            $table->date('destacado_ultima_vez')->nullable()->after('elegible_destacado');
            $table->date('fijado_hasta')->nullable()->after('destacado_ultima_vez');
        });
    }

    public function down(): void
    {
        Schema::table('directorio_emprendedores', function (Blueprint $table) {
            $table->dropColumn([
                'giro', 'municipio', 'sitio_web', 'egresado_iyem',
                'elegible_destacado', 'destacado_ultima_vez', 'fijado_hasta',
            ]);
        });
    }
};
