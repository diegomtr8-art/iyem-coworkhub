<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración a BBVA — la idempotencia deja de ser «de Stripe».
 *
 * `eventos_stripe` guardaba los eventos ya procesados para que un reintento no
 * activara dos veces una membresía. Con dos pasarelas conviviendo, la clave es
 * (pasarela, evento): para Stripe el id del evento; para BBVA, que no manda
 * eventos, `{transacción}:{estado}` de la consulta.
 *
 * Se crea la tabla nueva y se copian las filas en vez de renombrar: así el
 * índice único queda con su nombre correcto en MySQL y en SQLite.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventos_pasarela', function (Blueprint $table) {
            $table->id();
            $table->string('pasarela', 20);
            $table->string('evento_id');
            $table->string('tipo');
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();

            $table->unique(['pasarela', 'evento_id']);
        });

        if (Schema::hasTable('eventos_stripe')) {
            DB::table('eventos_stripe')->orderBy('id')->chunk(500, function ($filas) {
                DB::table('eventos_pasarela')->insert($filas->map(fn ($f) => [
                    'pasarela'     => 'stripe',
                    'evento_id'    => $f->stripe_event_id,
                    'tipo'         => $f->tipo,
                    'procesado_en' => $f->procesado_en,
                    'created_at'   => $f->created_at,
                    'updated_at'   => $f->updated_at,
                ])->all());
            });

            Schema::drop('eventos_stripe');
        }
    }

    public function down(): void
    {
        Schema::create('eventos_stripe', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('tipo');
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });

        DB::table('eventos_pasarela')->where('pasarela', 'stripe')->orderBy('id')->chunk(500, function ($filas) {
            DB::table('eventos_stripe')->insert($filas->map(fn ($f) => [
                'stripe_event_id' => $f->evento_id,
                'tipo'            => $f->tipo,
                'procesado_en'    => $f->procesado_en,
                'created_at'      => $f->created_at,
                'updated_at'      => $f->updated_at,
            ])->all());
        });

        Schema::dropIfExists('eventos_pasarela');
    }
};
