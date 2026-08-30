<x-correo titulo="Confirma tu nuevo correo — Nódico"
          resumen="Confirma esta dirección para terminar de cambiar el correo de tu cuenta.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, confirma tu nuevo correo
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Pediste usar esta dirección para entrar a Nódico. Confírmala y desde ese
        momento será el correo de tu cuenta. Hasta entonces, tu correo actual sigue
        funcionando.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Confirmar este correo
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        El enlace caduca en {{ $minutos }} minutos y solo sirve una vez. Si se te pasa,
        pídelo otra vez desde «Mi seguridad».
    </p>

    <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si el botón no funciona, copia y pega esta dirección en tu navegador:
    </p>
    <p style="margin:0 0 24px; font-size:12px; line-height:1.5; word-break:break-all;">
        <a href="{{ $url }}" style="color:#2E2D2C;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 28px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si no pediste este cambio, ignora este mensaje: sin confirmar, tu correo no
        cambia.
    </p>

</x-correo>
