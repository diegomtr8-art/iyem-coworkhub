<x-correo titulo="Tu pago está confirmado — Nódico"
          resumen="Confirmamos tu pago; tu membresía de Nódico está activa.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, tu pago está confirmado
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Recibimos y confirmamos tu pago. Tu membresía <strong>{{ $plan }}</strong> ya está activa@if ($vigencia)
        <strong> hasta el {{ $vigencia->translatedFormat('j \d\e F \d\e Y') }}</strong>@endif.
    </p>

    @if ($pideFactura)
        <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
            Tu <strong>factura está en proceso</strong>. En cuanto esté lista te llega por correo con el enlace para descargarla.
        </p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ route('portal.suscripcion') }}" style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Ir a mi membresía
                </a>
            </td>
        </tr>
    </table>

</x-correo>
