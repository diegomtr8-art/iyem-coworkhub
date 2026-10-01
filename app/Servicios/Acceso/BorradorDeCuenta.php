<?php

namespace App\Servicios\Acceso;

use App\Enums\EstadoPagoOrden;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Borrar la cuenta de un miembro, igual desde la web que desde la app.
 *
 * Tres cosas que un `delete()` a secas no hacía:
 *
 * 1. **Cancelar la renovación en la pasarela**: Stripe y también Openpay o
 *    BBVA (antes solo Stripe, y la pasarela del banco seguía cobrando cada mes
 *    a una cuenta que ya no existía). Si la pasarela no responde, no se borra
 *    nada: mejor pedir que lo intente de nuevo que dejar un cobro huérfano.
 *    En Openpay/BBVA se cancela al final del periodo: no hay más cobros, y el
 *    periodo ya pagado no se devuelve por cortarlo antes.
 * 2. **Cancelar las referencias sin pagar**, para que caja no confirme el pago
 *    de una cuenta borrada. Las pagadas se quedan (sin dueño): son registro
 *    fiscal.
 * 3. **Cerrar todas las sesiones de la app.**
 */
class BorradorDeCuenta
{
    /** @throws ValidationException si no se pudo cancelar la renovación */
    public function borrar(User $usuario): void
    {
        $this->cancelarRenovacion($usuario);

        DB::transaction(function () use ($usuario) {
            $usuario->ordenesPago()
                ->where('estado_pago', EstadoPagoOrden::Generada->value)
                ->update(['estado_pago' => EstadoPagoOrden::Cancelada->value]);

            $usuario->tokens()->delete();
            $usuario->delete();
        });
    }

    /** @throws ValidationException si la pasarela no respondió */
    public function cancelarRenovacion(User $usuario): void
    {
        $this->cancelarEnPasarelaDelBanco($usuario);

        $suscripcion = $usuario->subscription('default');

        if (! $suscripcion || $suscripcion->ended()) {
            return;
        }

        try {
            $suscripcion->cancelNow();
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'password' => 'No pudimos cancelar la renovación automática de tu membresía, así que no borramos '
                    . 'nada. Inténtalo en unos minutos o escríbenos.',
            ]);
        }
    }

    /**
     * Las suscripciones de Openpay/BBVA que todavía cobrarían, de cualquier
     * plataforma: la activa hoy puede no ser con la que se suscribió.
     *
     * @throws ValidationException
     */
    private function cancelarEnPasarelaDelBanco(User $usuario): void
    {
        $vivas = SuscripcionPasarela::where('user_id', $usuario->id)
            ->vivas()
            ->where('cancelar_al_final', false)
            ->get();

        foreach ($vivas as $suscripcion) {
            try {
                app(SuscripcionesOpenpay::class)->cancelarAlFinal($suscripcion);
            } catch (Throwable $e) {
                report($e);

                throw ValidationException::withMessages([
                    'password' => 'No pudimos cancelar la renovación automática de tu membresía, así que no borramos '
                        . 'nada. Inténtalo en unos minutos o escríbenos.',
                ]);
            }
        }
    }
}
