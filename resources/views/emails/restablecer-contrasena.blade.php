<x-correo titulo="Restablece tu contraseña — Nódico"
          resumen="Enlace para elegir una contraseña nueva.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, elige una contraseña nueva
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Alguien pidió restablecer la contraseña de esta cuenta. Si fuiste tú, usa el botón
        para elegir una nueva.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Restablecer mi contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        El enlace caduca en {{ $minutos }} minutos. Al cambiarla se cerrarán tus sesiones
        abiertas en otros dispositivos.
    </p>

    <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si el botón no funciona, copia y pega esta dirección en tu navegador:
    </p>
    <p style="margin:0 0 24px; font-size:12px; line-height:1.5; word-break:break-all;">
        <a href="{{ $url }}" style="color:#2E2D2C;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 28px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si no pediste esto, no tienes que hacer nada: tu contraseña actual sigue funcionando
        y nadie ha entrado a tu cuenta.
    </p>

</x-correo>
