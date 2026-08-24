<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('espacios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['escritorio', 'oficina_privada', 'sala_juntas', 'cabina_telefonica', 'lounge']);
            $table->integer('capacidad')->default(1);
            $table->decimal('precio_hora', 10, 2)->default(0);
            $table->json('amenidades')->nullable();
            $table->boolean('disponible')->default(true);
            $table->integer('piso')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espacios');
    }
};
