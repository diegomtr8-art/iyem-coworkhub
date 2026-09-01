<x-correo titulo="Tu factura de Nódico"
          resumen="Tu factura de Nódico ya está lista para descargar.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, tu factura está lista
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Contabilidad emitió la factura de tu pago (referencia <strong>{{ $referencia }}</strong>@if ($folio),
        folio fiscal <strong>{{ $folio }}</strong>@endif). Ya puedes descargar el PDF y el XML desde tu portal.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ $urlPagos }}" style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Descargar mi factura
                </a>
            </td>
        </tr>
    </table>

</x-correo>
