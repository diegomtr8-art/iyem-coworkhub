<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D — Segundo factor (TOTP).
 *
 * Tres piezas, y cada una guarda lo suyo de forma distinta según lo que haga
 * falta poder hacer con ello:
 *
 * - **El secreto** se guarda **cifrado**, no hasheado: hay que poder leerlo
 *   para calcular el código de cada minuto. Va con el cast `encrypted`, así que
 *   depende de `APP_KEY`.
 * - **Los códigos de recuperación** se guardan **hasheados**, como una
 *   contraseña: solo hay que comprobarlos, nunca mostrarlos. Por eso se enseñan
 *   una única vez y después solo se pueden regenerar.
 * - **Los dispositivos de confianza** guardan el hash del token que viaja en la
 *   cookie, para poder revocarlos desde «Mi seguridad».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cifrado: se necesita en claro para verificar cada código.
            $table->text('dos_factores_secreto')->nullable()->after('remember_token');

            // Nulo mientras no se haya confirmado con un código real. Un secreto
            // sin confirmar **no** activa el segundo factor: si alguien
            // escaneara mal el QR y se activara igual, quedaria fuera de su
            // cuenta sin remedio.
            $table->timestamp('dos_factores_confirmado_en')->nullable()->after('dos_factores_secreto');
        });

        Schema::create('codigos_recuperacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 del código. Los códigos son de 80 bits de entropía, así
            // que no hace falta un hash lento: con esa entropía no hay
            // diccionario que valga, y la búsqueda es un índice directo en vez
            // de un bucle de bcrypt por cada intento.
            $table->string('hash', 64)->unique();

            $table->timestamp('usado_en')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'usado_en']);
        });

        Schema::create('dispositivos_confiables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('token', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->text('agente')->nullable();
            $table->timestamp('expira_en');
            $table->timestamps();

            $table->index(['user_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_confiables');
        Schema::dropIfExists('codigos_recuperacion');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dos_factores_secreto', 'dos_factores_confirmado_en']);
        });
    }
};
