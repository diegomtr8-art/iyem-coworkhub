<?php

namespace App\Servicios\Pagos;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use App\Notifications\ReferenciaGenerada;
use Illuminate\Validation\ValidationException;

/**
 * Pagos con referencia (transferencia / efectivo): generar la orden.
 *
 * Sacado de `Portal\OrdenPagoController::generar()` el 22/09/2026 para que la
 * app genere referencias con las mismas reglas que la web. **No activa
 * membresías**: eso lo hace caja al confirmar el pago.
 */
class GeneradorDeReferencia
{
    /** Los datos que contabilidad necesita sí o sí para poder facturar. */
    public const FISCALES_OBLIGATORIOS = ['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal'];

    /**
     * @throws ValidationException `pide_factura` si faltan datos fiscales
     */
    public function generar(User $usuario, Plane $plan, MetodoReferencia $metodo, bool $pideFactura): OrdenPago
    {
        // Contabilidad no puede facturar sin datos fiscales completos. Se valida
        // en el servidor, no solo en la pantalla.
        if ($pideFactura && ! $this->datosFiscalesCompletos($usuario)) {
            throw ValidationException::withMessages([
                'pide_factura' => 'Para pedir factura necesitas completar tus datos fiscales. '
                    . 'Complétalos y vuelve a generar tu referencia.',
            ]);
        }

        $ref  = OrdenPago::nuevaReferencia();
        $dias = (int) config('nodico.pagos_referencia.vencimiento_dias', 7);

        $orden = new OrdenPago([
            'referencia'             => $ref['referencia'],
            'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id'                => $usuario->id,
            'plan_id'                => $plan->id,
            'monto'                  => (float) $plan->precio,
            'metodo'                 => $metodo,
            'pide_factura'           => $pideFactura,
            'estado_pago'            => EstadoPagoOrden::Generada,
            'estado_factura'         => $pideFactura ? EstadoFacturaOrden::Solicitada : EstadoFacturaOrden::NoSolicitada,
            'vence_el'               => now()->addDays($dias)->endOfDay(),
        ]);

        if ($pideFactura) {
            $orden->copiarFiscalesDe($usuario->datosFiscales);   // foto fiscal, no enlace vivo
        }

        $orden->save();

        // El correo con la referencia. Un fallo de correo no tumba el flujo.
        try {
            $usuario->notify(new ReferenciaGenerada($orden));
        } catch (\Throwable $e) {
            report($e);
        }

        return $orden;
    }

    public function datosFiscalesCompletos(?User $usuario): bool
    {
        $df = $usuario?->datosFiscales;

        if (! $df) {
            return false;
        }

        foreach (self::FISCALES_OBLIGATORIOS as $campo) {
            if (blank($df->{$campo})) {
                return false;
            }
        }

        return true;
    }

    /** Datos bancarios para una transferencia; `null` si no aplica. */
    public function datosBancarios(OrdenPago $orden): ?array
    {
        if ($orden->metodo !== MetodoReferencia::Transferencia) {
            return null;
        }

        $banco = config('nodico.pagos_referencia');

        return [
            'banco'        => $banco['banco'] ?? '',
            'clabe'        => $banco['clabe'] ?? '',
            'beneficiario' => $banco['beneficiario'] ?? '',
            'cuenta'       => $banco['cuenta'] ?? '',
        ];
    }
}
