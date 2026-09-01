<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Órdenes de pago con referencia (transferencia / efectivo).
 *
 * El centro del flujo que convive con Stripe: la tarjeta activa al instante sin
 * factura; esta ruta genera una referencia, la membresía se activa cuando
 * contabilidad confirma el dinero, y entonces se factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_pago', function (Blueprint $tabla) {
            $tabla->id();

            // La referencia que se dicta en caja y se escribe en el concepto del
            // banco. `referencia` es la de mostrar (NDC-7K4M2Q); la normalizada
            // (sin guiones ni ambigüedades, en mayúsculas) es la que se busca,
            // porque los bancos devuelven el concepto deformado.
            $tabla->string('referencia', 32)->unique();
            $tabla->string('referencia_normalizada', 32)->unique();

            $tabla->foreignId('user_id')->constrained('users');
            $tabla->foreignId('plan_id')->constrained('planes');
            $tabla->decimal('monto', 10, 2);
            $tabla->string('metodo', 16);                 // MetodoReferencia

            // Dos ciclos distintos, dos columnas.
            $tabla->string('estado_pago', 16)->default('generada');       // EstadoPagoOrden
            $tabla->string('estado_factura', 16)->default('no_solicitada'); // EstadoFacturaOrden
            $tabla->boolean('pide_factura')->default(false);

            $tabla->dateTime('vence_el');

            // Confirmación en caja.
            $tabla->foreignId('confirmada_por_user_id')->nullable()->constrained('users');
            $tabla->dateTime('confirmada_en')->nullable();
            $tabla->date('fecha_pago')->nullable();
            $tabla->decimal('monto_recibido', 10, 2)->nullable();
            $tabla->string('evidencia', 191)->nullable();   // folio de transferencia o de caja
            $tabla->text('nota')->nullable();               // monto distinto, encadenar, reactivación
            $tabla->string('motivo_cancelacion', 191)->nullable();

            // La suscripción que se creó al confirmarse.
            $tabla->foreignId('suscripcion_id')->nullable()->constrained('suscripciones');

            // Foto de los datos fiscales al momento de generar la orden (Fase 1.3):
            // una copia, no un enlace vivo, para no reescribir la historia si el
            // miembro cambia su RFC después.
            $tabla->string('fiscal_rfc', 20)->nullable();
            $tabla->string('fiscal_razon_social', 191)->nullable();
            $tabla->string('fiscal_regimen', 8)->nullable();
            $tabla->string('fiscal_uso_cfdi', 8)->nullable();
            $tabla->string('fiscal_cp', 10)->nullable();
            $tabla->string('fiscal_email', 191)->nullable();

            // Factura que sube contabilidad (Nódico no timbra).
            $tabla->string('folio_fiscal', 64)->nullable();
            $tabla->string('factura_pdf', 191)->nullable();
            $tabla->string('factura_xml', 191)->nullable();
            $tabla->dateTime('factura_emitida_en')->nullable();
            $tabla->dateTime('factura_enviada_en')->nullable();

            $tabla->timestamps();

            $tabla->index(['user_id', 'estado_pago']);
            $tabla->index(['estado_pago', 'vence_el']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_pago');
    }
};
