<?php

namespace App\Servicios\Membresias;

use App\Enums\AccionOperativa;
use App\Enums\EstadoCuenta;
use App\Models\EntradaBitacora;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta, renovación, cambio de plan y suspensión de membresías (Fase 3.3).
 *
 * Todo pasa por aquí y **todo queda en la bitácora**: son las acciones que
 * mueven dinero y acceso, y a los seis meses nadie recuerda por qué se suspendió
 * una cuenta si no está escrito.
 */
class GestorDeMembresias
{
    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    /**
     * Alta manual, para quien paga en efectivo o por transferencia.
     *
     * Cierra cualquier membresía activa anterior: dos suscripciones activas a la
     * vez harían que `membresiaVigente()` devolviera una u otra según el orden
     * de la consulta, y el miembro vería bolsas distintas en cada pantalla.
     */
    public function alta(
        User $miembro,
        Plane $plan,
        ?User $operativo,
        ?string $fechaInicio = null,
        ?float $precioPagado = null,
        ?string $nota = null,
        bool $cerrarAnterior = true,
    ): Suscripcion {
        return DB::transaction(function () use ($miembro, $plan, $operativo, $fechaInicio, $precioPagado, $nota, $cerrarAnterior) {
            $inicio = CarbonImmutable::parse($fechaInicio ?? 'today')->startOfDay();

            // Al encadenar una vigencia (pago por referencia decidido en caja) NO
            // se cierra la anterior: la nueva arranca cuando la actual termina.
            $anterior = $miembro->suscripciones()->where('estatus', 'Activa')->latest('fecha_inicio')->first();
            if ($cerrarAnterior) {
                $anterior?->update(['estatus' => 'Vencida']);
            }

            $suscripcion = Suscripcion::create([
                'user_id'       => $miembro->id,
                'plan_id'       => $plan->id,
                'fecha_inicio'  => $inicio->toDateString(),
                'fecha_fin'     => $this->vigenciaHasta($plan, $inicio)->toDateString(),
                'estatus'       => 'Activa',
                'precio_pagado' => $precioPagado ?? $plan->precio,
                'auto_renovar'  => false,
            ]);

            $suscripcion->setRelation('plan', $plan);

            // El ciclo se abre aquí y no se espera al comando de medianoche: la
            // persona está en el mostrador y quiere reservar hoy.
            $this->libro->abrirCiclo($suscripcion, $inicio);

            // Una cuenta pendiente que ya pagó pasa a activa: si no, sale del
            // mostrador con membresía y sin poder entrar.
            if ($miembro->estado === EstadoCuenta::Pendiente) {
                $miembro->cambiarEstado(EstadoCuenta::Activa);
            }

            EntradaBitacora::registrar(
                accion: AccionOperativa::AltaMembresia,
                descripcion: "Alta de {$plan->nombre} para {$miembro->name}, del "
                    . $suscripcion->fecha_inicio->toDateString() . ' al ' . $suscripcion->fecha_fin->toDateString() . '.',
                actor: $operativo,
                sujeto: $miembro,
                motivo: $nota,
                contexto: [
                    'suscripcion_id' => $suscripcion->id,
                    'plan'           => $plan->nombre,
                    'precio_pagado'  => (float) $suscripcion->precio_pagado,
                    'cerro_anterior' => $anterior?->id,
                ],
            );

            return $suscripcion;
        });
    }

    /** Extiende la vigencia sin cambiar de plan. */
    public function renovar(Suscripcion $suscripcion, ?User $operativo, ?float $precioPagado = null): Suscripcion
    {
        return DB::transaction(function () use ($suscripcion, $operativo, $precioPagado) {
            $plan = $suscripcion->plan;

            // Si aún está vigente se encadena al final; si ya venció, arranca hoy.
            // Encadenar una membresía vencida regalaría los días que estuvo caída.
            $fin    = CarbonImmutable::parse($suscripcion->fecha_fin);
            $arranque = $fin->gte(CarbonImmutable::today())
                ? $fin->addDay()
                : CarbonImmutable::today();

            $nueva = $this->alta(
                miembro: $suscripcion->user,
                plan: $plan,
                operativo: $operativo,
                fechaInicio: $arranque->toDateString(),
                precioPagado: $precioPagado ?? $plan->precio,
            );

            EntradaBitacora::registrar(
                accion: AccionOperativa::RenovacionMembresia,
                descripcion: "Renovación de {$plan->nombre} para {$suscripcion->user->name} "
                    . 'hasta el ' . $nueva->fecha_fin->toDateString() . '.',
                actor: $operativo,
                sujeto: $suscripcion->user,
                contexto: ['anterior' => $suscripcion->id, 'nueva' => $nueva->id],
            );

            return $nueva;
        });
    }

    /**
     * Cambio de plan.
     *
     * Decisión de Nódico (01/09/2026): **ciclo nuevo desde cero**. Se cierra la
     * suscripción anterior y se abre una con el plan nuevo y las bolsas
     * completas; lo consumido queda en el histórico del ciclo viejo.
     *
     * Es lo más fácil de explicar en el mostrador —«desde hoy tienes tus horas
     * nuevas»— y no obliga a nadie a hacer una regla de tres delante del
     * cliente. A cambio, quien cambia de plan a mitad de ciclo recibe la bolsa
     * entera del plan nuevo: es una concesión consciente, no un descuido, y por
     * eso queda anotada en la bitácora con el consumo que se dejó atrás.
     */
    public function cambiarDePlan(
        Suscripcion $suscripcion,
        Plane $planNuevo,
        ?User $operativo,
        string $motivo,
    ): Suscripcion {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Escribe por qué cambia de plan: queda en la bitácora.',
            ]);
        }

        if ($suscripcion->plan_id === $planNuevo->id) {
            throw ValidationException::withMessages([
                'plan_id' => 'Ese ya es su plan actual.',
            ]);
        }

        return DB::transaction(function () use ($suscripcion, $planNuevo, $operativo, $motivo) {
            $planViejo = $suscripcion->plan;

            // Foto del consumo que se deja atrás, para poder explicarlo después.
            $consumo = [];
            foreach (\App\Enums\BolsaDeHoras::cases() as $bolsa) {
                if ($bolsa->incluidaEn($planViejo)) {
                    $consumo[$bolsa->value] = $this->libro->consumoDelCiclo($suscripcion, $bolsa);
                }
            }

            $nueva = $this->alta(
                miembro: $suscripcion->user,
                plan: $planNuevo,
                operativo: $operativo,
                fechaInicio: CarbonImmutable::today()->toDateString(),
                precioPagado: $planNuevo->precio,
                nota: $motivo,
            );

            EntradaBitacora::registrar(
                accion: AccionOperativa::CambioPlan,
                descripcion: "Cambio de {$planViejo?->nombre} a {$planNuevo->nombre} para "
                    . $suscripcion->user->name . '. Ciclo nuevo desde cero.',
                actor: $operativo,
                sujeto: $suscripcion->user,
                motivo: $motivo,
                contexto: [
                    'plan_anterior'      => $planViejo?->nombre,
                    'plan_nuevo'         => $planNuevo->nombre,
                    'consumo_abandonado' => $consumo,
                    'suscripcion_anterior' => $suscripcion->id,
                    'suscripcion_nueva'  => $nueva->id,
                ],
            );

            return $nueva;
        });
    }

    /** Suspende la membresía. Exige motivo: el miembro va a preguntar por qué. */
    public function suspender(Suscripcion $suscripcion, ?User $operativo, string $motivo): Suscripcion
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Escribe por qué se suspende.',
            ]);
        }

        $suscripcion->update(['estatus' => 'Suspendida']);

        EntradaBitacora::registrar(
            accion: AccionOperativa::SuspensionCuenta,
            descripcion: "Membresía {$suscripcion->plan?->nombre} de {$suscripcion->user->name} suspendida.",
            actor: $operativo,
            sujeto: $suscripcion->user,
            motivo: $motivo,
            contexto: ['suscripcion_id' => $suscripcion->id],
        );

        return $suscripcion->refresh();
    }

    public function reactivar(Suscripcion $suscripcion, ?User $operativo): Suscripcion
    {
        $suscripcion->update(['estatus' => 'Activa']);

        EntradaBitacora::registrar(
            accion: AccionOperativa::ReactivacionCuenta,
            descripcion: "Membresía {$suscripcion->plan?->nombre} de {$suscripcion->user->name} reactivada.",
            actor: $operativo,
            sujeto: $suscripcion->user,
            contexto: ['suscripcion_id' => $suscripcion->id],
        );

        return $suscripcion->refresh();
    }

    /**
     * Hasta cuándo vale un plan comprado hoy.
     *
     * Los mensuales duran un mes de aniversario. Los de días (Day-Pass, Flex)
     * tienen una **ventana de 30 días** para consumirlos: decisión de Nódico
     * (29/08/2026).
     */
    public function vigenciaHasta(Plane $plan, CarbonImmutable $inicio): CarbonImmutable
    {
        if ($plan->tieneCiclosMensuales()) {
            return $inicio->addMonthNoOverflow()->subDay();
        }

        return $inicio->addDays((int) config('nodico.operacion.dias_ventana_planes_por_dia', 30));
    }
}
