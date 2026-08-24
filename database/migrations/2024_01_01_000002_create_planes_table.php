<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['dia', 'semana', 'mes', 'anual', 'horas']);
            $table->decimal('precio', 10, 2);
            $table->integer('horas_incluidas')->nullable();
            $table->integer('max_reservas_mes')->nullable();
            $table->boolean('acceso_24h')->default(false);
            $table->boolean('incluye_sala_juntas')->default(false);
            $table->string('color')->default('#7c3aed');
            $table->boolean('destacado')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
