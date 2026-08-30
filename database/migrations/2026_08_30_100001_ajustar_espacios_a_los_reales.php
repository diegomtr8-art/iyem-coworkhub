<?php

use App\Enums\TipoEspacio;
use App\Models\Espacio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 4.B — deja el catálogo de espacios como la realidad de Nódico:
 * 4 cubículos, 2 salas de juntas, 1 contenido, 1 fotografía, 1 coworking y
 * 2 salones. El seeder ya nace así; esto ajusta los entornos con datos.
 *
 * Dos operaciones, las dos idempotentes:
 *
 *   1. Sobran cubículos (había 5, van 4). El de más se retira **con cuidado**:
 *      la FK `espacio_id` es `cascadeOnDelete`, así que borrar un cubículo con
 *      reservas se llevaría por delante sus reservas y bloques sin avisar. Por
 *      eso solo se elimina el que está **vacío**; el que tenga reservas se
 *      desactiva (queda fuera del selector) y se anota, para que una persona
 *      mueva esas reservas antes de borrarlo. «Mover o avisar», nunca romper.
 *
 *   2. Faltan salas de juntas (había 1, van 2). Se crean las que falten.
 *
 * No renombra ni reordena lo que ya existe: los nombres que recepción conoce en
 * un entorno vivo se respetan. La forma final importa; la cosmética, no.
 */
return new class extends Migration {
    private const CUBICULOS_OBJETIVO   = 4;
    private const SALAS_JUNTAS_OBJETIVO = 2;

    public function up(): void
    {
        // Un entorno nuevo nace del seeder ya con la forma correcta, y ahí esta
        // migración corre **antes** que el seeder (con la tabla vacía). Si actuara,
        // crearía salas que el seeder volvería a crear: duplicados. Solo tiene
        // sentido sobre datos que ya existen.
        if (Espacio::count() === 0) {
            return;
        }

        $this->ajustarCubiculos();
        $this->asegurarSalasDeJuntas();
    }

    private function ajustarCubiculos(): void
    {
        $privados = Espacio::where('tipo', TipoEspacio::Privado->value)
            ->orderBy('id')
            ->get();

        $sobran = $privados->count() - self::CUBICULOS_OBJETIVO;
        if ($sobran <= 0) {
            return; // ya hay 4 o menos: nada que hacer.
        }

        // Se retiran de atrás hacia adelante (los de id más alto, los últimos
        // creados) para conservar la numeración que recepción ya conoce.
        $candidatos = $privados->reverse()->take($sobran);
        $conReservas = [];

        foreach ($candidatos as $cubiculo) {
            $tieneReservas = DB::table('reservas')->where('espacio_id', $cubiculo->id)->exists()
                || DB::table('bloques_reserva')->where('espacio_id', $cubiculo->id)->exists();

            if ($tieneReservas) {
                // No se borra: la cascada se llevaría las reservas. Se saca del
                // selector y se marca para que alguien las mueva a mano.
                $cubiculo->update(['disponible' => false, 'publicado' => false]);
                $conReservas[] = "#{$cubiculo->id} {$cubiculo->nombre}";
            } else {
                $cubiculo->delete();
            }
        }

        if ($conReservas !== []) {
            $lista = implode(', ', $conReservas);
            // Queda en la salida de migrate y en el log: no se pierde el aviso.
            echo "  AVISO: cubículos sobrantes con reservas, desactivados en vez "
                . "de borrados. Mueve sus reservas y elimínalos a mano: {$lista}\n";
            \Illuminate\Support\Facades\Log::warning(
                "Fase 4.B: cubículos sobrantes con reservas, desactivados: {$lista}"
            );
        }
    }

    private function asegurarSalasDeJuntas(): void
    {
        $existentes = Espacio::where('tipo', TipoEspacio::SalaJuntas->value)->count();
        $faltan = self::SALAS_JUNTAS_OBJETIVO - $existentes;

        for ($i = 0; $i < $faltan; $i++) {
            $numero = $existentes + $i + 1;
            Espacio::create([
                'nombre'      => "Sala de Juntas {$numero}",
                'tipo'        => TipoEspacio::SalaJuntas->value,
                'capacidad'   => 8,
                'precio_hora' => 0,
                'amenidades'  => ['WiFi', 'Pantalla', 'Pizarrón', 'Café'],
                'disponible'  => true,
                'piso'        => 2,
            ]);
        }
    }

    /**
     * Sin marcha atrás automática. Borrar salas de juntas o recrear cubículos
     * eliminados podría llevarse reservas creadas después, y ese daño no lo
     * arregla un `down()`. Si hay que revertir, se hace mirando los datos.
     */
    public function down(): void
    {
        // Intencionadamente vacío. Ver el comentario de arriba.
    }
};
