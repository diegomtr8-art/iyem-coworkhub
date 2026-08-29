<?php

namespace App\Servicios\Horas;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\MovimientoHoras;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El libro de horas (Fase 1.1). **Único punto de escritura de las bolsas.**
 *
 * Nadie debe volver a hacer `$suscripcion->increment('horas_sala_usadas', …)`.
 * Los contadores de `suscripciones` son caché: se escriben aquí, en la misma
 * transacción que el apunte, y se pueden reconstruir enteros desde el libro con
 * `php artisan nodico:reconstruir-saldos`.
 *
 * Lo que esto compra, y que un contador suelto no daba:
 *
 * - **auditoría**: cada hora consumida tiene su porqué, su fecha y su autor;
 * - **corrección segura**: recepción repone horas con un movimiento y una nota,
 *   nunca editando un contador a mano;
 * - **detección**: `verificarConsistencia()` compara caché contra suma y avisa
 *   si divergen, que es exactamente lo que nadie pudo hacer mientras el BUG-01
 *   corrompía saldos en silencio.
 */
class LibroDeHoras
{
    /**
     * Anota un movimiento y deja la caché al día.
     *
     * `$cantidad` va con signo: **positiva consume, negativa devuelve**.
     *
     * @param  string|null  $claveIdempotencia  Si se da y ya existe, no escribe
     *         nada y devuelve `null`. Es lo que hace idempotentes al reinicio de
     *         ciclo y al marcado de no-show.
     */
    public function registrar(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        float $cantidad,
        MotivoMovimiento $motivo,
        ?Reserva $reserva = null,
        ?SolicitudAsesoria $solicitud = null,
        ?User $autor = null,
        ?string $nota = null,
        ?string $claveIdempotencia = null,
        ?CarbonImmutable $en = null,
    ): ?MovimientoHoras {
        if ($motivo->exigeNota() && trim((string) $nota) === '') {
            throw new InvalidArgumentException(
                "Un movimiento de motivo «{$motivo->etiqueta()}» exige una nota que explique el porqué."
            );
        }

        return DB::transaction(function () use (
            $suscripcion, $bolsa, $cantidad, $motivo, $reserva, $solicitud, $autor, $nota, $claveIdempotencia, $en
        ) {
            try {
                $movimiento = MovimientoHoras::create([
                    'suscripcion_id'        => $suscripcion->id,
                    'bolsa'                 => $bolsa->value,
                    'cantidad'              => round($cantidad, 2),
                    'motivo'                => $motivo->value,
                    'ciclo_inicio'          => $suscripcion->cicloInicio($en)->toDateString(),
                    'reserva_id'            => $reserva?->id,
                    'solicitud_asesoria_id' => $solicitud?->id,
                    'creado_por_user_id'    => $autor?->id,
                    'nota'                  => $nota,
                    'clave_idempotencia'    => $claveIdempotencia,
                ]);
            } catch (QueryException $e) {
                // La clave ya estaba: la operación ya se hizo. No es un error.
                if ($claveIdempotencia !== null && $this->esClaveRepetida($e)) {
                    return null;
                }

                throw $e;
            }

            $this->sincronizarCache($suscripcion, $bolsa, $en);

            return $movimiento;
        });
    }

    /**
     * Consumo de una bolsa en el ciclo vigente, **calculado desde el libro**.
     * Este es el dato de verdad; el contador es su copia.
     */
    public function consumoDelCiclo(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        ?CarbonImmutable $en = null
    ): float {
        return round((float) MovimientoHoras::query()
            ->where('suscripcion_id', $suscripcion->id)
            ->deBolsa($bolsa)
            ->delCiclo($suscripcion->cicloInicio($en)->toDateString())
            ->sum('cantidad'), 2);
    }

    /** Saldo restante según el libro. `null` = bolsa ilimitada. */
    public function saldoDelCiclo(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        ?CarbonImmutable $en = null
    ): ?float {
        $cupo = $bolsa->cupo($suscripcion->plan);

        if ($cupo === null) {
            return $bolsa === BolsaDeHoras::Dias ? null : 0.0;
        }

        return max(0.0, round($cupo - $this->consumoDelCiclo($suscripcion, $bolsa, $en), 2));
    }

    /**
     * Rehace los contadores de una suscripción desde el libro.
     *
     * @return array<string, array{antes: float, despues: float}> solo las bolsas que cambiaron
     */
    public function reconstruirCache(Suscripcion $suscripcion, ?CarbonImmutable $en = null): array
    {
        $cambios = [];

        foreach (BolsaDeHoras::cases() as $bolsa) {
            $antes   = $bolsa->consumo($suscripcion);
            $despues = $this->consumoDelCiclo($suscripcion, $bolsa, $en);

            if (abs($antes - $despues) >= 0.01) {
                $cambios[$bolsa->value] = ['antes' => $antes, 'despues' => $despues];
            }

            $suscripcion->{$bolsa->campoConsumo()} = $despues;
        }

        $suscripcion->save();

        return $cambios;
    }

    /**
     * Compara caché contra libro sin escribir nada.
     *
     * @return array<string, array{cache: float, libro: float}> bolsas que divergen
     */
    public function verificarConsistencia(Suscripcion $suscripcion, ?CarbonImmutable $en = null): array
    {
        $divergencias = [];

        foreach (BolsaDeHoras::cases() as $bolsa) {
            $cache = $bolsa->consumo($suscripcion);
            $libro = $this->consumoDelCiclo($suscripcion, $bolsa, $en);

            if (abs($cache - $libro) >= 0.01) {
                $divergencias[$bolsa->value] = ['cache' => $cache, 'libro' => $libro];
            }
        }

        return $divergencias;
    }

    /**
     * Marca el arranque de un ciclo. Idempotente por la clave: correrlo dos
     * veces el mismo día no reinicia dos veces.
     *
     * No hace falta restar nada: el consumo se cuenta por `ciclo_inicio`, así
     * que al cambiar de ciclo la suma parte de cero sola. El movimiento es un
     * marcador de cantidad 0, y su valor es el rastro —permite saber que el
     * ciclo se abrió, cuándo, y recuperar un día en que el servidor estuvo
     * caído sin duplicar nada.
     */
    public function abrirCiclo(Suscripcion $suscripcion, ?CarbonImmutable $en = null): bool
    {
        $ciclo   = $suscripcion->cicloInicio($en);
        $abiertos = 0;

        foreach ($suscripcion->plan?->bolsasIncluidas() ?? [] as $bolsa) {
            $movimiento = $this->registrar(
                suscripcion: $suscripcion,
                bolsa: $bolsa,
                cantidad: 0,
                motivo: MotivoMovimiento::ReinicioCiclo,
                nota: 'Ciclo del ' . $ciclo->translatedFormat('j \d\e F \d\e Y')
                    . ' al ' . $suscripcion->cicloFin($en)->translatedFormat('j \d\e F \d\e Y') . '.',
                claveIdempotencia: $this->claveDeCiclo($suscripcion, $bolsa, $ciclo),
                en: $en,
            );

            if ($movimiento !== null) {
                $abiertos++;
            }
        }

        if ($abiertos > 0) {
            // Ciclo nuevo: los contadores del anterior no valen para este.
            $this->reconstruirCache($suscripcion, $en);
        }

        return $abiertos > 0;
    }

    public function claveDeCiclo(Suscripcion $suscripcion, BolsaDeHoras $bolsa, CarbonImmutable $ciclo): string
    {
        return "reinicio:{$suscripcion->id}:{$bolsa->value}:{$ciclo->toDateString()}";
    }

    /** Copia al contador lo que dice el libro para esa bolsa. */
    private function sincronizarCache(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        ?CarbonImmutable $en = null
    ): void {
        $suscripcion->forceFill([
            $bolsa->campoConsumo() => $this->consumoDelCiclo($suscripcion, $bolsa, $en),
        ])->save();
    }

    private function esClaveRepetida(QueryException $e): bool
    {
        return (int) ($e->errorInfo[0] ?? 0) === 23000
            && str_contains($e->getMessage(), 'clave_idempotencia');
    }
}
