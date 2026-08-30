<?php

namespace App\Servicios\Emprendedores;

use App\Models\DirectorioEmprendedor;
use Carbon\CarbonImmutable;

/**
 * Fase 4.H — el «emprendedor de la semana» que rota solo.
 *
 * La rotación es **justa**: el próximo es siempre el que lleva más tiempo sin
 * salir (o el que nunca ha salido), así nadie se repite hasta que todos hayan
 * pasado. `destacado_ultima_vez` es la cola: al destacar a alguien se le sella
 * la fecha de hoy y pasa al final.
 *
 * Se puede **fijar** uno a mano para una semana concreta (cuando hay algo que
 * celebrar) sin romper el ciclo: mientras el fijado siga vigente, la rotación no
 * toca nada. Y si no queda ningún elegible, mantiene al último y avisa: la
 * sección nunca se queda vacía.
 */
class RotacionDeEmprendedor
{
    /**
     * Rota al siguiente. Devuelve un resumen de lo que pasó.
     *
     * @return array{estado: string, destacado: ?string, aviso: ?string}
     */
    public function rotar(?CarbonImmutable $hoy = null): array
    {
        $hoy = $hoy ?? CarbonImmutable::today();

        // 1. ¿Hay uno fijado a mano y todavía vigente? Se respeta.
        $fijado = DirectorioEmprendedor::where('activo', true)
            ->whereNotNull('fijado_hasta')
            ->whereDate('fijado_hasta', '>=', $hoy->toDateString())
            ->first();

        if ($fijado) {
            $this->dejarSoloDestacado($fijado, $hoy, sellar: false);

            return ['estado' => 'fijado', 'destacado' => $fijado->nombre, 'aviso' => null];
        }

        $elegibles = DirectorioEmprendedor::elegibles()->count();

        // 2. Sin elegibles: se mantiene lo que haya y se avisa.
        if ($elegibles === 0) {
            $actual = DirectorioEmprendedor::deLaSemana()->first();

            return [
                'estado'    => 'sin_elegibles',
                'destacado' => $actual?->nombre,
                'aviso'     => 'No hay emprendedores elegibles para destacar. Se mantiene el actual.',
            ];
        }

        $actual = DirectorioEmprendedor::deLaSemana()->first();

        // 3. El próximo: el elegible que lleva más sin salir (nulls primero), y
        //    que no sea el actual salvo que sea el único elegible.
        $siguiente = DirectorioEmprendedor::elegibles()
            ->when($actual && $elegibles > 1, fn ($q) => $q->whereKeyNot($actual->id))
            ->orderByRaw('destacado_ultima_vez IS NOT NULL') // nulls primero
            ->orderBy('destacado_ultima_vez')
            ->orderBy('id')
            ->first();

        // Al actual se le sella la fecha de salida y deja de estar destacado.
        if ($actual && $actual->id !== $siguiente->id) {
            $actual->update(['destacado_semana' => false, 'destacado_ultima_vez' => $hoy->toDateString()]);
        }

        $this->dejarSoloDestacado($siguiente, $hoy, sellar: true);

        return ['estado' => 'rotado', 'destacado' => $siguiente->nombre, 'aviso' => null];
    }

    /**
     * El calendario de rotación: quién está esta semana y quién seguiría las
     * próximas, simulando la cola sin tocar la base.
     *
     * @return array<int, array{semana: string, nombre: string}>
     */
    public function calendario(int $semanas = 6, ?CarbonImmutable $hoy = null): array
    {
        $hoy = $hoy ?? CarbonImmutable::today();

        // Cola simulada: (id => última_vez), ordenada como rota de verdad.
        $cola = DirectorioEmprendedor::elegibles()
            ->orderByRaw('destacado_ultima_vez IS NOT NULL')
            ->orderBy('destacado_ultima_vez')
            ->orderBy('id')
            ->get(['id', 'nombre', 'destacado_ultima_vez'])
            ->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre,
                              'ultima' => $e->destacado_ultima_vez?->toDateString()])
            ->all();

        if ($cola === []) {
            return [];
        }

        $plan = [];
        $lunes = $hoy->startOfWeek();

        for ($i = 0; $i < $semanas; $i++) {
            // El primero de la cola (más antiguo) sale; se le sella y va al final.
            usort($cola, fn ($a, $b) => [$a['ultima'] === null ? 0 : 1, (string) $a['ultima'], $a['id']]
                                    <=> [$b['ultima'] === null ? 0 : 1, (string) $b['ultima'], $b['id']]);
            $sale = $cola[0];
            $semana = $lunes->addWeeks($i);
            $plan[] = ['semana' => $semana->toDateString(), 'nombre' => $sale['nombre']];
            $cola[0]['ultima'] = $semana->toDateString();
        }

        return $plan;
    }

    /** Fija un emprendedor como destacado hasta el fin de la semana dada. */
    public function fijar(DirectorioEmprendedor $emprendedor, ?CarbonImmutable $hasta = null): void
    {
        $hasta = ($hasta ?? CarbonImmutable::today())->endOfWeek();
        $emprendedor->update(['fijado_hasta' => $hasta->toDateString()]);
        $this->dejarSoloDestacado($emprendedor, CarbonImmutable::today(), sellar: false);
    }

    /** Deja a uno como el único con `destacado_semana`, opcionalmente sellando su salida. */
    private function dejarSoloDestacado(DirectorioEmprendedor $emprendedor, CarbonImmutable $hoy, bool $sellar): void
    {
        DirectorioEmprendedor::where('destacado_semana', true)
            ->whereKeyNot($emprendedor->id)
            ->update(['destacado_semana' => false]);

        $emprendedor->update([
            'destacado_semana'     => true,
            'destacado_ultima_vez' => $sellar ? $hoy->toDateString() : $emprendedor->destacado_ultima_vez?->toDateString(),
        ]);
    }
}
