<x-correo titulo="No pudimos cobrar tu membresía — Nódico"
          resumen="El cobro de tu membresía de Nódico no se pudo procesar.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, no pudimos cobrar tu membresía
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        El banco rechazó el cargo de tu membresía de Nódico ({{ $motivo }}). Mientras
        tanto, tu cuenta queda suspendida y no podrás reservar.
    </p>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Para reactivarla, entra a tu portal y actualiza tu método de pago desde
        <strong>Mi membresía</strong>. Si crees que es un error, escríbenos y lo
        revisamos contigo.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ route('portal.suscripcion') }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Ir a mi membresía
                </a>
            </td>
        </tr>
    </table>

</x-correo>
