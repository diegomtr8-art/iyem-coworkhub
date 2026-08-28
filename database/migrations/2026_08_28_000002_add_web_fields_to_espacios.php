<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SQLite (usado en las pruebas) no tiene ENUM: la columna es TEXT y acepta el valor nuevo.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('coworking','privado','sala_juntas','contenido','fotografia','salon_eventos','escritorio','oficina_privada','cabina_telefonica','lounge')");
        }

        Schema::table('espacios', function (Blueprint $table) {
            $table->text('descripcion')->nullable()->after('nombre');
            $table->string('medidas')->nullable()->after('descripcion');
            $table->json('incluye')->nullable()->after('amenidades');
            $table->integer('cap_herradura')->nullable()->after('capacidad');
            $table->integer('cap_mesas')->nullable()->after('cap_herradura');
            $table->integer('cap_escuela')->nullable()->after('cap_mesas');
            $table->integer('cap_auditorio')->nullable()->after('cap_escuela');
            $table->string('imagen')->nullable()->after('incluye');
            $table->boolean('publicado')->default(false)->after('disponible');
            $table->integer('orden')->default(0)->after('publicado');
        });
    }

    public function down(): void
    {
        Schema::table('espacios', function (Blueprint $table) {
            $table->dropColumn([
                'descripcion', 'medidas', 'incluye', 'cap_herradura', 'cap_mesas',
                'cap_escuela', 'cap_auditorio', 'imagen', 'publicado', 'orden',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('coworking','privado','sala_juntas','contenido','fotografia','escritorio','oficina_privada','cabina_telefonica','lounge')");
        }
    }
};
