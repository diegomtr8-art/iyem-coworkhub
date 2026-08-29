<x-correo titulo="Tu enlace de acceso — Nódico"
          resumen="Entra a Nódico sin escribir tu contraseña.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, aquí está tu enlace
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Pediste entrar a Nódico sin contraseña. Este botón te lleva directo a tu portal.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Entrar a Nódico
                </a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px; background-color:#F4F1EA; border:1px solid #E8E1D1;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#2E2D2C;">
                    · Caduca en <strong>{{ $minutos }} minutos</strong> y sirve <strong>una sola vez</strong>.
                </p>
                <p style="margin:0; font-size:13px; line-height:1.6; color:#2E2D2C;">
                    · Solo funciona en el navegador desde el que lo pediste.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si el botón no funciona, copia y pega esta dirección en el mismo navegador:
    </p>
    <p style="margin:0 0 24px; font-size:12px; line-height:1.5; word-break:break-all;">
        <a href="{{ $url }}" style="color:#2E2D2C;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 28px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si no pediste este enlace, ignóralo: no hace falta que hagas nada y nadie ha entrado
        a tu cuenta. Si te llegan varios seguidos sin haberlos pedido, escríbenos.
    </p>

</x-correo>
