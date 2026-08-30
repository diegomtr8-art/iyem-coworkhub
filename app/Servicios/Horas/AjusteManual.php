<?php

namespace App\Servicios\Horas;

use App\Enums\AccionOperativa;
use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\EntradaBitacora;
use App\Models\MovimientoHoras;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recepción repone o descuenta horas a mano (Fase 3.2).
 *
 * Existe por una razón concreta: **que nadie tenga que editar un contador**.
 * Antes, arreglar el saldo de alguien significaba escribir un número en
 * `horas_sala_usadas` y rezar; ahora es un movimiento del libro, con su motivo,
 * su autor y su fecha, y el saldo se recalcula solo.
 *
 * El motivo es obligatorio en los dos sitios que lo comprueban —el libro y la
 * bitácora—, y no es redundancia: un ajuste sin porqué es indistinguible de un
 * error, y a los seis meses nadie sabe cuál era cuál.
 */
class AjusteManual
{
    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    /**
     * @param  float  $horas  Positivo **repone** a la persona (baja el consumo),
     *         negativo se lo descuenta. Se expresa desde el punto de vista de
     *         quien está en el mostrador —«le repongo 2 horas»— y no desde el
     *         signo interno del libro, que es al revés.
     */
    public function aplicar(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        float $horas,
        string $motivo,
        User $operativo,
    ): MovimientoHoras {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Escribe por qué ajustas las horas. Queda en la bitácora.',
            ]);
        }

        if (abs($horas) < 0.01) {
            throw ValidationException::withMessages([
                'horas' => 'El ajuste no puede ser de cero horas.',
            ]);
        }

        if (! $bolsa->incluidaEn($suscripcion->plan)) {
            throw ValidationException::withMessages([
                'bolsa' => "El plan {$suscripcion->plan?->nombre} no incluye {$bolsa->etiqueta()}.",
            ]);
        }

        return DB::transaction(function () use ($suscripcion, $bolsa, $horas, $motivo, $operativo) {
            $antes = $this->libro->saldoDelCiclo($suscripcion, $bolsa);

            // El libro cuenta consumo: reponer horas es **restar** consumo.
            $movimiento = $this->libro->registrar(
                suscripcion: $suscripcion,
                bolsa: $bolsa,
                cantidad: -$horas,
                motivo: MotivoMovimiento::AjusteManual,
                autor: $operativo,
                nota: $motivo,
            );

            $despues = $this->libro->saldoDelCiclo($suscripcion->refresh(), $bolsa);

            $verbo = $horas > 0 ? 'Repuso' : 'Descontó';

            EntradaBitacora::registrar(
                accion: AccionOperativa::AjusteHoras,
                descripcion: sprintf(
                    '%s %s h de %s a %s. Saldo: %s → %s.',
                    $verbo,
                    rtrim(rtrim(number_format(abs($horas), 2, '.', ''), '0'), '.'),
                    $bolsa->etiqueta(),
                    $suscripcion->user->name,
                    $antes === null ? 'ilimitado' : $antes,
                    $despues === null ? 'ilimitado' : $despues,
                ),
                actor: $operativo,
                sujeto: $suscripcion->user,
                motivo: $motivo,
                contexto: [
                    'suscripcion_id' => $suscripcion->id,
                    'bolsa'          => $bolsa->value,
                    'horas'          => $horas,
                    'saldo_antes'    => $antes,
                    'saldo_despues'  => $despues,
                    'movimiento_id'  => $movimiento?->id,
                ],
            );

            return $movimiento;
        });
    }
}
