<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('telefono', 40)->nullable();
            $table->string('email', 150);
            $table->string('empresa', 150)->nullable();
            $table->string('asunto', 200)->nullable();
            $table->text('comentarios');
            $table->string('ip', 45)->nullable();
            $table->boolean('atendido')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
