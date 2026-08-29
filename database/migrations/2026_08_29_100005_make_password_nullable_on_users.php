<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * C — La contrasena pasa a ser opcional.
 *
 * Quien entra con Google **no tiene contrasena**, y forzar una a sus espaldas
 * seria inventarle una credencial que no eligio ni conoce. Su metodo de acceso
 * es la identidad externa; se le ofrece poner contrasena desde el perfil, sin
 * obligarlo.
 *
 * El guard de Laravel ya trata un hash nulo como «no coincide» (BcryptHasher
 * comprueba la longitud antes de llamar a `password_verify`), asi que una
 * cuenta sin contrasena simplemente no puede entrar por el formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Una cuenta sin contrasena no cabe en el esquema anterior; se le pone
        // un hash imposible de adivinar en vez de perder la fila.
        DB::table('users')->whereNull('password')->update([
            'password' => bcrypt(Str::random(64)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
