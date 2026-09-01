<x-correo titulo="Tu referencia de pago — Nódico"
          resumen="Tu referencia {{ $referencia }} por ${{ number_format($monto, 2) }}.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, aquí está tu referencia
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Generamos tu referencia para pagar por {{ mb_strtolower($metodo) }}. Estos son los datos:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 22px; border:2px solid #2E2D2C;">
        <tr>
            <td style="background-color:#1A1918; padding:18px 24px;">
                <p style="margin:0; font-family:'Courier New',Courier,monospace; font-size:11px; letter-spacing:0.16em; text-transform:uppercase; color:#FFE124;">Referencia</p>
                <p style="margin:6px 0 0; font-size:30px; font-weight:800; letter-spacing:0.02em; color:#ffffff;">{{ $referencia }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 24px; background-color:#ffffff;">
                <p style="margin:0; font-size:16px; color:#2E2D2C;">Monto exacto: <strong style="font-size:18px;">${{ number_format($monto, 2) }} MXN</strong></p>
                <p style="margin:6px 0 0; font-size:15px; color:#2E2D2C;">Paga antes del <strong>{{ $vence->translatedFormat('j \d\e F \d\e Y') }}</strong>.</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        En una transferencia, <strong>pon la referencia en el concepto</strong>. Si pagas en efectivo,
        pásate por recepción y dicta tu referencia.
    </p>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Tu membresía se activa <strong>en cuanto confirmemos tu pago</strong>.@if ($pideFactura) Tu factura llega después, por correo.@endif
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ route('portal.pagos') }}" style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Ver mis pagos e instrucciones
                </a>
            </td>
        </tr>
    </table>

</x-correo>
