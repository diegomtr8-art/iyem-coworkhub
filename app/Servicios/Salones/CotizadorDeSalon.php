<?php

namespace App\Servicios\Salones;

use App\Enums\TipoMontaje;
use App\Models\Espacio;

/**
 * Calcula el importe de una renta de salón (Fase 3.5).
 *
 * Está aparte del controlador porque el mismo cálculo lo hacen dos sitios: la
 * pantalla, que lo recalcula en vivo mientras recepción escribe, y el servidor
 * al guardar. Si vivieran en dos lados, el número de la pantalla y el guardado
 * podrían no coincidir, y eso es un precio mal cobrado.
 *
 * ## El coffee break y el hueco entre tramos
 *
 * Nódico tiene dos tarifas: $45 por persona hasta 25 pax y $35 desde 100.
 * **Entre 26 y 99 no hay tarifa**, y Nódico decidió (01/09/2026) que ese tramo
 * se cotiza a mano. Este servicio lo dice explícitamente en vez de inventar
 * una interpolación: un precio inventado se cobra de verdad.
 */
class CotizadorDeSalon
{
    /**
     * @param  float|null  $precioCoffeePersona  Fuerza un precio por persona.
     *         Es lo que usa recepción en el tramo sin tarifa.
     *
     * @return array<string, mixed>
     */
    public function cotizar(
        ?Espacio $salon,
        float $horas,
        bool $conCoffee = false,
        int $coffeePersonas = 0,
        ?float $precioCoffeePersona = null,
        float $descuento = 0,
        ?float $precioHora = null,
    ): array {
        $precioHora = $precioHora
            ?? (float) ($salon?->precio_hora ?: config('nodico.salones.precio_hora', 600));

        $horas          = max(0, round($horas, 2));
        $subtotalSalon  = round($precioHora * $horas, 2);

        $tarifa         = $this->tarifaCoffee($coffeePersonas);
        $precioPersona  = $precioCoffeePersona ?? $tarifa['precio'];
        $subtotalCoffee = $conCoffee && $coffeePersonas > 0 && $precioPersona !== null
            ? round($precioPersona * $coffeePersonas, 2)
            : 0.0;

        $descuento = max(0, round($descuento, 2));
        $total     = max(0, round($subtotalSalon + $subtotalCoffee - $descuento, 2));

        return [
            'precio_hora'           => $precioHora,
            'horas'                 => $horas,
            'subtotal_salon'        => $subtotalSalon,

            'con_coffee_break'      => $conCoffee,
            'coffee_personas'       => $coffeePersonas,
            'coffee_precio_persona' => (float) ($precioPersona ?? 0),
            'subtotal_coffee'       => $subtotalCoffee,

            // Cuando el tramo no tiene tarifa, la pantalla tiene que pedir el
            // precio en vez de enseñar un cero que parece gratis.
            'coffee_requiere_precio' => $conCoffee
                && $coffeePersonas > 0
                && $tarifa['precio'] === null
                && $precioCoffeePersona === null,
            'coffee_nota'           => $tarifa['nota'],

            'descuento' => $descuento,
            'total'     => $total,
        ];
    }

    /**
     * Tarifa del coffee break para un número de personas.
     *
     * @return array{precio: float|null, nota: string|null}
     */
    public function tarifaCoffee(int $personas): array
    {
        if ($personas <= 0) {
            return ['precio' => null, 'nota' => null];
        }

        $tramos = config('nodico.salones.coffee', []);

        foreach ($tramos as $tramo) {
            if (isset($tramo['hasta_pax']) && $personas <= $tramo['hasta_pax']) {
                return [
                    'precio' => (float) $tramo['precio'],
                    'nota'   => "Tarifa de hasta {$tramo['hasta_pax']} personas.",
                ];
            }
        }

        foreach ($tramos as $tramo) {
            if (isset($tramo['desde_pax']) && $personas >= $tramo['desde_pax']) {
                return [
                    'precio' => (float) $tramo['precio'],
                    'nota'   => "Tarifa desde {$tramo['desde_pax']} personas.",
                ];
            }
        }

        return [
            'precio' => null,
            'nota'   => 'Este número de personas queda entre las dos tarifas conocidas: '
                . 'escribe el precio por persona que acordaste.',
        ];
    }

    /**
     * Avisos sobre el aforo, para que nadie prometa un montaje que no cabe.
     *
     * @return array<int, string>
     */
    public function avisosDeAforo(?Espacio $salon, ?TipoMontaje $montaje, int $personas): array
    {
        $avisos = [];

        if (! $salon || $personas <= 0) {
            return $avisos;
        }

        if ($montaje) {
            $capacidad = $montaje->capacidadEn($salon);

            if ($capacidad !== null && $personas > $capacidad) {
                $avisos[] = "En montaje de {$montaje->etiqueta()} caben {$capacidad} personas y "
                    . "estás cotizando {$personas}.";

                // Qué montaje sí lo resuelve: es la pregunta siguiente.
                $alternativas = collect(TipoMontaje::cases())
                    ->filter(fn (TipoMontaje $m) => ($m->capacidadEn($salon) ?? 0) >= $personas)
                    ->map(fn (TipoMontaje $m) => $m->etiqueta() . ' (' . $m->capacidadEn($salon) . ')');

                if ($alternativas->isNotEmpty()) {
                    $avisos[] = 'Sí caben en: ' . $alternativas->implode(', ') . '.';
                }
            }
        }

        if ($salon->capacidad && $personas > $salon->capacidad) {
            $avisos[] = "El aforo máximo de {$salon->nombre} es de {$salon->capacidad} personas.";
        }

        return $avisos;
    }
}
