<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas con acceso que no son miembros: empleados del IYEM y prestadores de
 * servicio social. Ficha ligera para reconocerlas y amarrar su rostro de Smart
 * Pass, de modo que su entrada quede registrada a su nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas_acceso', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('categoria', 20)->index();   // empleado | servicio_social
            $tabla->string('nombre');
            $tabla->string('correo')->nullable();
            $tabla->string('telefono', 30)->nullable();
            $tabla->string('identificador', 60)->nullable();  // núm. de empleado / matrícula
            $tabla->string('puesto')->nullable();             // puesto o carrera/institución
            $tabla->date('inicio')->nullable();               // alta / inicio del servicio
            $tabla->date('fin')->nullable();                  // fin del periodo (servicio social)
            $tabla->boolean('activo')->default(true);
            $tabla->text('notas')->nullable();

            // Rostro de Smart Pass (FaceID). Único: un rostro no puede estar en dos
            // fichas. NO va en $fillable del modelo (se asigna con forceFill).
            $tabla->unsignedBigInteger('smartpass_person_id')->nullable()->unique();

            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas_acceso');
    }
};
