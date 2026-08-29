<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.3 — datos fiscales del miembro.
 *
 * Tabla propia y **no columnas en `users`**: son datos personales de otra
 * naturaleza, con otro control de acceso y otra vida útil. Metidos en `users`
 * viajarían en cada `$request->user()` de cada petición del sitio, y acabarían
 * serializados en respuestas de Inertia que no tienen nada que ver con
 * facturación.
 *
 * El RFC se guarda **normalizado**: mayúsculas, sin espacios ni guiones. Sin eso,
 * «xaxx-010101-000» y «XAXX010101000» son dos contribuyentes distintos para el
 * sistema y el mismo para el SAT.
 *
 * Nódico **no timbra**: esto se recopila, se resguarda y se le entrega a
 * contabilidad del IYEM. Toda lectura y todo cambio quedan en la bitácora.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('datos_fiscales', function (Blueprint $tabla) {
            $tabla->id();

            // Uno por persona. El único índice de acceso legítimo.
            $tabla->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // 12 posiciones para persona moral, 13 para física.
            $tabla->string('rfc', 13);
            $tabla->string('razon_social');

            // Claves del catálogo del SAT, no texto libre. Ver `config/sat.php`.
            $tabla->string('regimen_fiscal', 4);
            $tabla->string('uso_cfdi', 4);

            $tabla->string('codigo_postal', 5);

            // Puede no ser el correo de la cuenta: muchas veces es el de
            // contabilidad de su empresa.
            $tabla->string('email_facturacion');

            // Quién los tocó por última vez. La bitácora guarda el historial
            // completo; esto es para poder enseñarlo en la ficha sin otra consulta.
            $tabla->foreignId('actualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();

            $tabla->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datos_fiscales');
    }
};
