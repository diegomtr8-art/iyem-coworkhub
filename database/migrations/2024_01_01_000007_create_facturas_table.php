<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suscripcion_id')->nullable()->constrained('suscripciones')->nullOnDelete();
            $table->string('folio')->unique();
            $table->string('concepto');
            $table->date('fecha');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('estatus', ['Pendiente', 'Pagada', 'Cancelada'])->default('Pendiente');
            $table->enum('metodo_pago', ['Efectivo', 'Transferencia', 'Tarjeta'])->nullable();
            $table->date('fecha_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
