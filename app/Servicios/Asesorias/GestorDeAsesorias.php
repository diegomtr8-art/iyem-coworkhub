<?php

namespace App\Servicios\Asesorias;

use App\Enums\BolsaDeHoras;
use App\Enums\EstadoAsesoria;
use App\Enums\MotivoMovimiento;
use App\Models\Asesor;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Las transiciones de una solicitud de asesoría, y el único sitio que mueve la
 * bolsa de asesoría (Fase 1.2).
 *
 * La regla que ordena todo esto: **las horas se descuentan al confirmar**.
 * Pedir no cuesta nada, porque quien decide si la asesoría ocurre es el
 * operativo, no el miembro. Cobrarle la bolsa por pedir sería cobrarle por una
 * decisión que no es suya.
 */
class GestorDeAsesorias
{
    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    /**
     * El miembro pide. No se descuenta nada todavía, pero sí se comprueba que
     * tenga sentido pedirlo: sin bolsa suficiente, la solicitud solo generaría
     * un rechazo y una decepción.
     */
    public function solicitar(
        Suscripcion $suscripcion,
        string $tema,
        string $diaPreferido,
        string $horarioPreferido,
        float $horas = 1,
        ?int $asesorPreferidoId = null,
        ?User $solicitante = null,
    ): SolicitudAsesoria {
        // Nodo Match (22/09/2026): el acompañante pide contra la bolsa
        // compartida, pero la solicitud es **suya**. Sin solicitante explícito
        // se asume el titular, que es lo que hacía siempre.
        $userId = $solicitante?->id ?? $suscripcion->user_id;

        $plan = $suscripcion->plan;

        if (! BolsaDeHoras::Asesoria->incluidaEn($plan)) {
            throw ValidationException::withMessages([
                'general' => "Tu plan {$plan?->nombre} no incluye asesoría IYEM.",
            ]);
        }

        // Día de calendario de Mérida, igual que `Reserva::hoy()` contra el que se compara.
        $dia = CarbonImmutable::parse($diaPreferido, \App\Models\Reserva::zonaDelCalendario())->startOfDay();

        if ($dia->lt(\App\Models\Reserva::hoy())) {
            throw ValidationException::withMessages([
                'dia_preferido' => 'Elige un día que no haya pasado.',
            ]);
        }

        if ($dia->gt(CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), \App\Models\Reserva::zonaDelCalendario()))) {
            throw ValidationException::withMessages([
                'dia_preferido' => 'Ese día cae fuera de la vigencia de tu membresía.',
            ]);
        }

        $this->verificarCupo($suscripcion, $horas, $dia, null, $userId);

        return SolicitudAsesoria::create([
            'user_id'             => $userId,
            'suscripcion_id'      => $suscripcion->id,
            'tema'                => $tema,
            'dia_preferido'       => $dia->toDateString(),
            'horario_preferido'   => $horarioPreferido,
            'horas'               => $horas,
            'estado'              => EstadoAsesoria::Solicitada,
            'asesor_preferido_id' => $asesorPreferidoId,
        ]);
    }

    /**
     * El operativo confirma: asigna asesor, fija la fecha real y **aquí sí** se
     * descuenta la bolsa.
     */
    public function confirmar(
        SolicitudAsesoria $solicitud,
        User $operativo,
        string $fechaConfirmada,
        ?Asesor $asesor = null,
        ?string $asesorNombre = null,
        ?string $notas = null,
    ): SolicitudAsesoria {
        return DB::transaction(function () use ($solicitud, $operativo, $fechaConfirmada, $asesorNombre, $asesor, $notas) {
            $fresca = SolicitudAsesoria::whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if ($fresca->estado !== EstadoAsesoria::Solicitada) {
                throw ValidationException::withMessages([
                    'estado' => 'Esa solicitud ya está ' . $fresca->estado->etiqueta() . '.',
                ]);
            }

            if ($asesor === null && trim((string) $asesorNombre) === '') {
                throw ValidationException::withMessages([
                    'asesor_id' => 'Asigna un asesor antes de confirmar.',
                ]);
            }

            $this->verificarCupo(
                $fresca->suscripcion,
                $fresca->horas,
                CarbonImmutable::parse($fechaConfirmada),
                $fresca->id,
                $fresca->user_id,
            );

            // El nombre se **congela** aquí, aunque venga del catálogo: si al
            // asesor lo dan de baja o le cambian el nombre, esta asesoría tiene
            // que seguir diciendo quién la dio.
            $fresca->update([
                'estado'               => EstadoAsesoria::Confirmada,
                'asesor_nombre'        => $asesor?->nombre ?? $asesorNombre,
                'asesor_id'            => $asesor?->id,
                'fecha_confirmada'     => $fechaConfirmada,
                'notas_operativo'      => $notas,
                'atendida_por_user_id' => $operativo->id,
                'atendida_en'          => now(),
            ]);

            $this->libro->registrar(
                suscripcion: $fresca->suscripcion,
                bolsa: BolsaDeHoras::Asesoria,
                cantidad: $fresca->horas,
                motivo: MotivoMovimiento::Asesoria,
                solicitud: $fresca,
                autor: $operativo,
                nota: 'Asesoría confirmada con ' . ($asesor?->nombre ?? $asesorNombre) . '.',
            );

            $fresca->refresh();

            // App móvil: aviso push de la confirmación, ya guardada. Un fallo
            // del aviso no deshace la confirmación.
            DB::afterCommit(function () use ($fresca) {
                try {
                    $fresca->user?->notify(new \App\Notifications\AvisoDeLaApp(
                        'reservas',
                        'Tu asesoría está confirmada',
                        'Con ' . $fresca->asesorLegible() . ', el '
                            . $fresca->fecha_confirmada?->translatedFormat('j \d\e F, H:i') . ' h.',
                        ['pantalla' => 'asesoria', 'id' => $fresca->id],
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return $fresca;
        });
    }

    /** El operativo rechaza. No se descuenta nada: nunca llegó a descontarse. */
    public function rechazar(SolicitudAsesoria $solicitud, User $operativo, string $motivo): SolicitudAsesoria
    {
        if ($solicitud->estado !== EstadoAsesoria::Solicitada) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede rechazar una solicitud pendiente.',
            ]);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'notas_operativo' => 'Di por qué se rechaza: el miembro va a leerlo.',
            ]);
        }

        $solicitud->update([
            'estado'               => EstadoAsesoria::Rechazada,
            'notas_operativo'      => $motivo,
            'atendida_por_user_id' => $operativo->id,
            'atendida_en'          => now(),
        ]);

        return $solicitud->refresh();
    }

    /**
     * Cancelación. Devuelve las horas solo si estaban descontadas y se cancela
     * con la misma antelación que rige las reservas.
     */
    public function cancelar(SolicitudAsesoria $solicitud, User $autor): SolicitudAsesoria
    {
        return DB::transaction(function () use ($solicitud, $autor) {
            $fresca = SolicitudAsesoria::whereKey($solicitud->getKey())->lockForUpdate()->firstOrFail();

            if ($fresca->estado->esFinal()) {
                throw ValidationException::withMessages([
                    'estado' => 'Esa solicitud ya está ' . $fresca->estado->etiqueta() . '.',
                ]);
            }

            $devolver = $fresca->estado->consumeBolsa() && $this->aTiempo($fresca);

            if ($devolver) {
                $this->libro->registrar(
                    suscripcion: $fresca->suscripcion,
                    bolsa: BolsaDeHoras::Asesoria,
                    cantidad: -$fresca->horas,
                    motivo: MotivoMovimiento::CancelacionAsesoria,
                    solicitud: $fresca,
                    autor: $autor,
                    nota: 'Asesoría cancelada con antelación suficiente.',
                );
            }

            $fresca->update(['estado' => EstadoAsesoria::Cancelada]);

            return $fresca->refresh();
        });
    }

    /** La asesoría ocurrió. Las horas ya estaban descontadas al confirmar. */
    public function marcarRealizada(SolicitudAsesoria $solicitud, User $operativo): SolicitudAsesoria
    {
        if ($solicitud->estado !== EstadoAsesoria::Confirmada) {
            throw ValidationException::withMessages([
                'estado' => 'Solo una asesoría confirmada se puede dar por realizada.',
            ]);
        }

        $solicitud->update([
            'estado'               => EstadoAsesoria::Realizada,
            'atendida_por_user_id' => $operativo->id,
            'atendida_en'          => now(),
        ]);

        return $solicitud->refresh();
    }

    /**
     * Bolsa del ciclo y tope diario. El tope se cuenta sobre las solicitudes que
     * ya consumen bolsa ese mismo día, no sobre todas: una rechazada no ocupa.
     */
    private function verificarCupo(
        Suscripcion $suscripcion,
        float $horas,
        CarbonImmutable $dia,
        ?int $excluyendoId = null,
        ?int $userId = null,
    ): void {
        $restante = $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Asesoria);

        if ($restante !== null && $restante < $horas) {
            throw ValidationException::withMessages([
                'horas' => "Te quedan {$restante} h de asesoría en este ciclo y estás pidiendo {$horas} h.",
            ]);
        }

        $tope = BolsaDeHoras::Asesoria->topeDiario($suscripcion->plan);

        if ($tope === null) {
            return;
        }

        // El tope diario es **por persona** (decisión del 22/09/2026): en un
        // Match, titular y acompañante tienen cada uno su hora al día, aunque
        // las dos salgan de la misma bolsa.
        $eseDia = SolicitudAsesoria::where('suscripcion_id', $suscripcion->id)
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->whereIn('estado', [EstadoAsesoria::Confirmada->value, EstadoAsesoria::Realizada->value])
            ->when($excluyendoId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where(fn ($q) => $q
                ->whereDate('fecha_confirmada', $dia->toDateString())
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('fecha_confirmada')
                    ->whereDate('dia_preferido', $dia->toDateString())))
            ->sum('horas');

        if ($eseDia + $horas > $tope) {
            throw ValidationException::withMessages([
                'dia_preferido' => "Tu plan permite {$tope} h de asesoría al día y ese día ya hay {$eseDia} h.",
            ]);
        }
    }

    private function aTiempo(SolicitudAsesoria $solicitud): bool
    {
        $margen = (int) config('nodico.operacion.horas_para_cancelar_sin_penalizacion', 2);
        $cuando = $solicitud->fecha_confirmada
            ? CarbonImmutable::parse($solicitud->fecha_confirmada)
            : CarbonImmutable::parse($solicitud->dia_preferido)->startOfDay();

        return $cuando->gte(CarbonImmutable::now()->addHours($margen));
    }
}
