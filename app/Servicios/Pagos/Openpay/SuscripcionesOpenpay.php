<?php

namespace App\Servicios\Pagos\Openpay;

use App\Enums\EstadoCuenta;
use App\Models\CargoPasarela;
use App\Models\ClientePasarela;
use App\Models\EventoPasarela;
use App\Models\Plane;
use App\Models\PlanPasarela;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use App\Servicios\Pagos\ActivadorDeMembresia;
use App\Servicios\Pagos\Importe;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * El cobro automático de cada periodo en Openpay (paso 3,
 * docs/PAGOS-OPENPAY.md). Es el trabajo que Cashier hacía con Stripe.
 *
 * **Cómo se reparte el cobro:**
 *  1. El primer periodo lo cobra Nódico al momento, con la tarjeta tecleada en
 *     Nódico (y 3-D Secure si el antifraude lo pide). Es un cargo normal, que
 *     se confirma consultando la API como todos.
 *  2. Confirmado ese cargo, el servidor da de alta la suscripción en Openpay
 *     con la tarjeta guardada y **prueba hasta el último día del periodo
 *     pagado**: Openpay cobra el día siguiente, cuando empieza el nuevo.
 *  3. De ahí en adelante cobra Openpay, reintenta 2 veces si falla y deja la
 *     suscripción `unpaid` al agotar los reintentos (decisión de Diego,
 *     29-sep-2026). **Nódico no reintenta encima.**
 *
 * **Cómo se entera Nódico** de cada renovación o rechazo: `sincronizar()`
 * consulta la suscripción (lo corre `nodico:sincronizar-suscripciones` y, en
 * el paso 4, cada webhook). Nada se decide por lo que llegue de fuera:
 *  - `active` con un periodo más → se renueva la membresía (una sola vez por
 *    periodo);
 *  - `past_due` / `unpaid` → se suspende por impago en el primer rechazo, como
 *    hoy; si un reintento se cobra después, la cuenta vuelve a quedar activa;
 *  - `cancelled` → deja de renovarse.
 *
 * Openpay no avisa cuando cancela una suscripción sola al agotar reintentos
 * (no hay evento para eso), por eso la consulta periódica no es opcional.
 */
class SuscripcionesOpenpay
{
    public function __construct(private readonly ActivadorDeMembresia $activador)
    {
    }

    // ── Planes ──────────────────────────────────────────────────────────────

    /**
     * El plan de Openpay vigente para este plan de Nódico, solo si está
     * sincronizado a su precio actual. Si el precio cambió y no se ha
     * sincronizado, no se ofrece la renovación automática: sería cobrar cada
     * mes un precio que ya no es el del plan.
     */
    public function planDe(Plane $plan, string $pasarela): ?PlanPasarela
    {
        $vigente = PlanPasarela::vigente($plan, $pasarela);

        return $vigente && Importe::iguales($vigente->importe, Importe::enPesos($plan)) ? $vigente : null;
    }

    /**
     * Crea en Openpay los planes que falten (o cuyo precio cambió) para los
     * planes de Nódico que se cobran solos cada mes.
     *
     * @return list<array{plan: string, accion: string, id: ?string}>
     *
     * @throws ErrorDeOpenpay
     */
    public function sincronizarPlanes(string $pasarela, bool $simular = false): array
    {
        $api = ClienteOpenpay::para($pasarela);
        $resultado = [];

        $planes = Plane::where('cobro_recurrente', true)->where('precio', '>', 0)->orderBy('precio')->get();

        foreach ($planes as $plan) {
            if (! $plan->tieneCiclosMensuales()) {
                $resultado[] = ['plan' => $plan->nombre, 'accion' => 'omitido: no es mensual', 'id' => null];

                continue;
            }

            if ($actual = $this->planDe($plan, $pasarela)) {
                $resultado[] = ['plan' => $plan->nombre, 'accion' => 'ya estaba', 'id' => $actual->plan_pasarela_id];

                continue;
            }

            $importe = Importe::enPesos($plan);

            if ($simular) {
                $resultado[] = ['plan' => $plan->nombre, 'accion' => "se crearía por \${$importe}", 'id' => null];

                continue;
            }

            // https://documents.openpay.mx/docs/api/index.html#crear-un-nuevo-plan
            $creado = $api->crearPlan([
                'name'               => mb_substr("Nódico — {$plan->nombre}", 0, 80),
                'amount'             => $importe,
                'currency'           => 'MXN',
                'repeat_every'       => 1,
                'repeat_unit'        => 'month',
                'retry_times'        => (int) $api->ajuste('reintentos', 2),
                'status_after_retry' => (string) $api->ajuste('estado_tras_reintentos', 'unpaid'),
                'trial_days'         => 0,
            ]);

            PlanPasarela::where('plan_id', $plan->id)->where('pasarela', $pasarela)->update(['activo' => false]);
            PlanPasarela::create([
                'plan_id'          => $plan->id,
                'pasarela'         => $pasarela,
                'plan_pasarela_id' => $creado['id'],
                'importe'          => $importe,
                'activo'           => true,
            ]);

            Log::info('Openpay: plan creado.', ['plan' => $plan->id, 'openpay' => $creado['id'], 'importe' => $importe]);
            $resultado[] = ['plan' => $plan->nombre, 'accion' => "creado por \${$importe}", 'id' => $creado['id']];
        }

        return $resultado;
    }

    // ── Alta ────────────────────────────────────────────────────────────────

    /**
     * Da de alta la suscripción de un cargo de primer periodo ya confirmado.
     * Idempotente: una suscripción por cargo (índice único). Si Openpay no
     * responde, no pasa nada: `nodico:sincronizar-suscripciones` lo reintenta.
     *
     * @throws ErrorDeOpenpay
     */
    public function asegurarAlta(CargoPasarela $cargo): ?SuscripcionPasarela
    {
        if (! $cargo->suscribir || $cargo->estado !== CargoPasarela::COMPLETADO || $cargo->pasarela !== 'openpay') {
            return null;
        }

        return Cache::lock("suscripcion:alta:{$cargo->id}", 30)->block(15, function () use ($cargo) {
            if ($ya = SuscripcionPasarela::where('cargo_id', $cargo->id)->first()) {
                return $ya;
            }

            $miembro = $cargo->usuario;
            $plan = $cargo->plan;
            $planPasarela = $plan ? $this->planDe($plan, $cargo->pasarela) : null;
            $cliente = $miembro ? ClientePasarela::where('user_id', $miembro->id)->where('pasarela', $cargo->pasarela)->first() : null;

            if (! $miembro || ! $planPasarela || ! $cliente?->tieneTarjeta()) {
                Log::error('Openpay: no se pudo dar de alta la suscripción (falta persona, plan sincronizado o tarjeta).', [
                    'cargo' => $cargo->id, 'plan_sincronizado' => (bool) $planPasarela, 'tarjeta' => (bool) $cliente?->tieneTarjeta(),
                ]);

                return null;
            }

            $membresia = $miembro->suscripciones()
                ->where('estatus', 'Activa')
                ->where('plan_id', $plan->id)
                ->whereDate('fecha_fin', '>=', now()->toDateString())
                ->latest('fecha_fin')
                ->first();

            if (! $membresia) {
                Log::error('Openpay: cargo de suscripción confirmado sin membresía vigente del plan.', ['cargo' => $cargo->id]);

                return null;
            }

            // Prueba hasta el último día pagado: Openpay cobra el siguiente.
            $respuesta = ClienteOpenpay::para($cargo->pasarela)->crearSuscripcion($cliente->cliente_id, [
                'plan_id'        => $planPasarela->plan_pasarela_id,
                'source_id'      => $cliente->tarjeta_id,
                'trial_end_date' => $membresia->fecha_fin->toDateString(),
            ], $cargo->ip_cliente ?: '127.0.0.1');

            $suscripcion = SuscripcionPasarela::create([
                'user_id'          => $miembro->id,
                'plan_id'          => $plan->id,
                'pasarela'         => $cargo->pasarela,
                'suscripcion_id'   => $respuesta['id'],
                'cliente_id'       => $cliente->cliente_id,
                'tarjeta_id'       => $cliente->tarjeta_id,
                'plan_pasarela_id' => $planPasarela->plan_pasarela_id,
                'cargo_id'         => $cargo->id,
                ...$this->camposDe($respuesta),
                'sincronizada_en'  => now(),
            ]);

            $membresia->update(['auto_renovar' => true]);

            Log::info('Openpay: suscripción dada de alta.', [
                'cargo' => $cargo->id, 'suscripcion' => $suscripcion->suscripcion_id, 'primer_cobro' => $respuesta['charge_date'] ?? null,
            ]);

            return $suscripcion;
        });
    }

    // ── Consultas y cambios ─────────────────────────────────────────────────

    /** La suscripción viva de esta persona en la pasarela, si tiene. */
    public function vivaDe(User $usuario, string $pasarela): ?SuscripcionPasarela
    {
        return SuscripcionPasarela::where('user_id', $usuario->id)
            ->where('pasarela', $pasarela)
            ->vivas()
            ->latest('id')
            ->first();
    }

    /**
     * Deja de cobrar al final del periodo pagado. La membresía sigue hasta su
     * fecha: la persona ya pagó ese periodo.
     *
     * @throws ErrorDeOpenpay
     */
    public function cancelarAlFinal(SuscripcionPasarela $suscripcion): void
    {
        $respuesta = ClienteOpenpay::para($suscripcion->pasarela)->actualizarSuscripcion(
            $suscripcion->cliente_id, $suscripcion->suscripcion_id, ['cancel_at_period_end' => true], '127.0.0.1',
        );

        $suscripcion->update([...$this->camposDe($respuesta), 'sincronizada_en' => now()]);

        if ($suscripcion->usuario) {
            $this->activador->detenerRenovacion($suscripcion->usuario);
        }

        Log::info('Openpay: renovación cancelada al final del periodo.', ['suscripcion' => $suscripcion->suscripcion_id]);
    }

    /**
     * Deshace la cancelación mientras siga viva. `false` si ya no se puede.
     *
     * @throws ErrorDeOpenpay
     */
    public function reactivar(SuscripcionPasarela $suscripcion): bool
    {
        if (! $suscripcion->viva() || ! $suscripcion->cancelar_al_final) {
            return false;
        }

        $respuesta = ClienteOpenpay::para($suscripcion->pasarela)->actualizarSuscripcion(
            $suscripcion->cliente_id, $suscripcion->suscripcion_id, ['cancel_at_period_end' => false], '127.0.0.1',
        );

        $suscripcion->update([...$this->camposDe($respuesta), 'sincronizada_en' => now()]);
        $this->marcarRenovacion($suscripcion);

        Log::info('Openpay: renovación reactivada.', ['suscripcion' => $suscripcion->suscripcion_id]);

        return true;
    }

    // ── Sincronización ──────────────────────────────────────────────────────

    /**
     * Consulta la suscripción en Openpay y actúa según lo que diga. Cada
     * renovación y cada rechazo se procesan una sola vez por periodo, aunque
     * se sincronice muchas veces (y aunque lleguen webhooks repetidos).
     *
     * @throws ErrorDeOpenpay si Openpay no responde; se reintenta después.
     */
    public function sincronizar(SuscripcionPasarela $suscripcion): SuscripcionPasarela
    {
        try {
            $datos = ClienteOpenpay::para($suscripcion->pasarela)
                ->obtenerSuscripcion($suscripcion->cliente_id, $suscripcion->suscripcion_id);
        } catch (ErrorDeOpenpay $e) {
            if ($e->http !== 404) {
                throw $e;
            }

            // Ya no existe en Openpay (se borró desde su panel).
            $datos = ['status' => 'cancelled'];
        }

        $miembro = $suscripcion->usuario;
        $plan = $suscripcion->plan;
        $estado = strtolower((string) ($datos['status'] ?? $suscripcion->estado));
        $periodo = (int) ($datos['current_period_number'] ?? $suscripcion->periodo_actual);
        $anterior = $suscripcion->estado;

        // Se cobró un periodo más: renovar la membresía.
        if ($estado === 'active' && $periodo > $suscripcion->periodo_actual && $miembro && $plan) {
            EventoPasarela::procesarUnaVez($suscripcion->pasarela, "{$suscripcion->suscripcion_id}:periodo:{$periodo}", 'subscription.renewed',
                function () use ($suscripcion, $miembro, $plan) {
                    $precio = PlanPasarela::where('plan_pasarela_id', $suscripcion->plan_pasarela_id)->value('importe');
                    $this->activador->renovar($miembro, $plan, $precio !== null ? (float) $precio : null);

                    // Suspendida por un rechazo que un reintento ya cobró.
                    if ($suscripcion->en_impago && $miembro->refresh()->estado === EstadoCuenta::Suspendida) {
                        $miembro->cambiarEstado(EstadoCuenta::Activa);
                    }

                    Log::info('Openpay: renovación cobrada; membresía extendida.', ['suscripcion' => $suscripcion->suscripcion_id]);
                });

            $suscripcion->en_impago = false;
        }

        // Cobro rechazado: se suspende en el primer rechazo del periodo, como
        // hoy. Openpay reintenta solo; Nódico no reintenta encima.
        if (in_array($estado, ['past_due', 'unpaid'], true) && ! $suscripcion->en_impago && $miembro) {
            EventoPasarela::procesarUnaVez($suscripcion->pasarela, "{$suscripcion->suscripcion_id}:impago:{$periodo}", 'subscription.charge.failed',
                function () use ($miembro, $suscripcion) {
                    $this->activador->suspenderPorImpago(
                        $miembro,
                        'No pudimos cobrar la renovación de tu membresía. Actualiza tu tarjeta o paga por referencia.',
                    );
                    Log::warning('Openpay: cobro de renovación rechazado; membresía suspendida.', ['suscripcion' => $suscripcion->suscripcion_id]);
                });

            $suscripcion->en_impago = true;
        }

        if ($estado === 'cancelled' && $anterior !== 'cancelled' && $miembro) {
            $this->activador->detenerRenovacion($miembro);
            Log::info('Openpay: suscripción cancelada; deja de renovarse.', ['suscripcion' => $suscripcion->suscripcion_id]);
        }

        $suscripcion->fill([...$this->camposDe($datos), 'sincronizada_en' => now()])->save();

        if ($suscripcion->viva() && ! $suscripcion->cancelar_al_final) {
            $this->marcarRenovacion($suscripcion);
        }

        return $suscripcion;
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    /** Lo que se guarda de la respuesta de Openpay. */
    private function camposDe(array $datos): array
    {
        return array_filter([
            'estado'            => isset($datos['status']) ? strtolower((string) $datos['status']) : null,
            'cancelar_al_final' => $datos['cancel_at_period_end'] ?? null,
            'periodo_fin'       => $datos['period_end_date'] ?? null,
            'proximo_cobro'     => $datos['charge_date'] ?? null,
            'periodo_actual'    => $datos['current_period_number'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** La membresía vigente de esa persona y plan se renueva sola. */
    private function marcarRenovacion(SuscripcionPasarela $suscripcion): void
    {
        $suscripcion->usuario?->suscripciones()
            ->where('estatus', 'Activa')
            ->where('plan_id', $suscripcion->plan_id)
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->update(['auto_renovar' => true]);
    }
}
