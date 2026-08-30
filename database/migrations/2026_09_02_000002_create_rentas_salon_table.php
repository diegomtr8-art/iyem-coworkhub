<?php

use App\Enums\EstadoRentaSalon;
use App\Enums\TipoMontaje;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.5 — renta de los salones Yucatán Emprende.
 *
 * Los salones **no funcionan como las salas**: no consumen bolsa de nadie, se
 * cotizan por hora, se les añade coffee break y los contrata gente que muchas
 * veces no es miembro. Por eso son su propia tabla y no una `reserva` con un
 * campo más: meterlos ahí obligaría a que la mitad de las columnas de
 * `reservas` fueran nulas y a que cada consulta de cupos las excluyera a mano.
 *
 * El **anticipo es un monto libre** que escribe recepción (decisión de Nódico,
 * 01/09/2026): no hay porcentaje fijo, se negocia por evento. El total sí lo
 * calcula el sistema, porque es donde se equivoca la gente.
 *
 * El **precio del coffee break se congela** en la fila. Los tramos conocidos
 * son $45 hasta 25 pax y $35 desde 100; entre medias lo cotiza recepción. Si
 * mañana cambian las tarifas, una cotización de hace tres meses tiene que
 * seguir diciendo lo que se cobró.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('rentas_salon', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('espacio_id')->constrained('espacios')->cascadeOnDelete();

            // Cliente externo: casi nunca es un miembro. Si lo es, se enlaza.
            $tabla->string('cliente_nombre');
            $tabla->string('cliente_email')->nullable();
            $tabla->string('cliente_telefono', 30)->nullable();
            $tabla->string('cliente_empresa')->nullable();
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // De dónde salió: el formulario «Hablemos» del sitio público.
            $tabla->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();

            $tabla->string('evento_nombre')->nullable();
            $tabla->date('fecha')->nullable();
            $tabla->time('hora_inicio')->nullable();
            $tabla->time('hora_fin')->nullable();

            $tabla->enum('montaje', TipoMontaje::valores())->nullable();
            $tabla->integer('personas')->default(0);

            // Importes. Todos congelados en la fila: una cotización vieja tiene
            // que seguir diciendo lo que se cotizó, no lo que costaría hoy.
            $tabla->decimal('precio_hora', 10, 2)->default(0);
            $tabla->decimal('horas', 5, 2)->default(0);
            $tabla->decimal('subtotal_salon', 10, 2)->default(0);

            $tabla->boolean('con_coffee_break')->default(false);
            $tabla->integer('coffee_personas')->default(0);
            $tabla->decimal('coffee_precio_persona', 10, 2)->default(0);
            $tabla->decimal('subtotal_coffee', 10, 2)->default(0);

            $tabla->decimal('descuento', 10, 2)->default(0);
            $tabla->decimal('total', 10, 2)->default(0);

            // Monto libre: lo negocia y lo escribe recepción.
            $tabla->decimal('anticipo', 10, 2)->default(0);
            $tabla->date('anticipo_pagado_el')->nullable();

            $tabla->enum('estado', EstadoRentaSalon::valores())
                ->default(EstadoRentaSalon::Cotizacion->value);

            $tabla->text('notas')->nullable();

            $tabla->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            // El bloqueo de agenda que genera al confirmarse: mientras exista,
            // el salón está ocupado para todo lo demás.
            $tabla->foreignId('bloqueo_id')->nullable()->constrained('bloqueos_espacio')->nullOnDelete();

            $tabla->timestamps();

            $tabla->index(['estado', 'fecha']);
            $tabla->index(['espacio_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentas_salon');
    }
};
