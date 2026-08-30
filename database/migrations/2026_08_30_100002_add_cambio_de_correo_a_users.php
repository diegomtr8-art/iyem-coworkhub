<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.C — cambio de correo con verificación de la dirección **nueva**.
 *
 * El correo no se toca hasta que la persona confirma la dirección nueva. Hasta
 * entonces la solicitud vive aquí, aparte del `email` real:
 *
 *  - `email_nuevo`         la dirección solicitada, todavía sin aplicar.
 *  - `email_nuevo_token`   hash del token del enlace (nunca el token en claro).
 *  - `email_nuevo_expira_en` caducidad; una solicitud vieja no vale.
 *
 * Así, si alguien entra con una sesión ajena y pide el cambio, el dueño real
 * recibe el aviso en su dirección de siempre y el correo no ha cambiado: le da
 * tiempo a reaccionar. El correo solo se mueve cuando alguien con acceso a la
 * dirección nueva pulsa el enlace.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_nuevo')->nullable()->after('email');
            $table->string('email_nuevo_token', 64)->nullable()->after('email_nuevo');
            $table->timestamp('email_nuevo_expira_en')->nullable()->after('email_nuevo_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_nuevo', 'email_nuevo_token', 'email_nuevo_expira_en']);
        });
    }
};
