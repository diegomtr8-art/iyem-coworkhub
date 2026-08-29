<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2.2 — lo que le falta al perfil del miembro.
 *
 * El **contacto de emergencia** no es un extra: Nódico es un espacio físico
 * donde la gente pasa jornadas enteras, y el día que alguien se desmaye,
 * recepción necesita a quién llamar. Es de los pocos datos que se piden por una
 * razón que no tiene que ver con el negocio.
 *
 * Las preferencias de notificación van con `default(true)`: quien se da de alta
 * quiere saber de sus reservas. Apagarlas es una decisión que toma la persona,
 * no un estado inicial que tenga que descubrir para enterarse de sus cosas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->string('contacto_emergencia_nombre')->nullable()->after('ocupacion');
            $tabla->string('contacto_emergencia_telefono', 30)->nullable()->after('contacto_emergencia_nombre');
            $tabla->string('contacto_emergencia_parentesco', 60)->nullable()->after('contacto_emergencia_telefono');

            $tabla->boolean('notif_reservas')->default(true)->after('contacto_emergencia_parentesco');
            $tabla->boolean('notif_membresia')->default(true)->after('notif_reservas');
            $tabla->boolean('notif_comunidad')->default(true)->after('notif_membresia');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'contacto_emergencia_nombre',
                'contacto_emergencia_telefono',
                'contacto_emergencia_parentesco',
                'notif_reservas',
                'notif_membresia',
                'notif_comunidad',
            ]);
        });
    }
};
