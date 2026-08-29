<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C — Identidades externas vinculadas a una cuenta.
 *
 * Tabla aparte y **no** columnas sueltas en `users` (`google_id`, `apple_id`…)
 * por tres razones concretas:
 *
 * 1. Una persona puede tener Google **y** contraseña a la vez, y mañana Apple,
 *    sin migrar nada ni añadir una columna por proveedor.
 * 2. El único compuesto `(proveedor, proveedor_id)` impide **en la base** que
 *    dos cuentas de Nódico reclamen la misma cuenta de Google. Con columnas
 *    sueltas eso solo se puede vigilar desde el código, y basta un camino
 *    nuevo para saltárselo.
 * 3. Guarda cuándo se vinculó y el último acceso, que es lo que se necesita el
 *    día que alguien pregunta «¿cómo entró esta persona?».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identidades_sociales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('proveedor', 30);

            // El identificador que da el proveedor. Es lo único estable: el
            // correo de una cuenta de Google puede cambiar, su `sub` no.
            $table->string('proveedor_id');

            $table->string('correo')->nullable();
            $table->string('avatar', 2048)->nullable();

            $table->timestamp('ultimo_acceso_en')->nullable();
            $table->timestamps();

            // Una identidad externa pertenece a una sola cuenta de Nódico.
            $table->unique(['proveedor', 'proveedor_id']);

            // Y una cuenta no puede tener dos identidades del mismo proveedor.
            $table->unique(['user_id', 'proveedor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identidades_sociales');
    }
};
