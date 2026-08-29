<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C — Enlaces mágicos: entrar sin contraseña con un enlace de un solo uso.
 *
 * El token se guarda **hasheado**, igual que una contraseña. Un enlace mágico
 * en claro en la base de datos es una credencial de acceso completa: quien
 * pudiera leer esta tabla entraría a cualquier cuenta sin saber nada más.
 *
 * `huella` ata el enlace al navegador que lo pidió. Sin eso, un enlace
 * reenviado o filtrado del buzón sirve desde cualquier parte, que es la
 * objeción clásica a este mecanismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enlaces_magicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // SHA-256 del token que viaja en la URL.
            $table->string('token', 64)->unique();

            // SHA-256 del secreto que quedó en la sesión de quien lo pidió.
            $table->string('huella', 64);

            $table->string('ip', 45)->nullable();
            $table->timestamp('expira_en');

            // Marcado al usarse: un enlace mágico sirve **una vez**.
            $table->timestamp('usado_en')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_magicos');
    }
};
