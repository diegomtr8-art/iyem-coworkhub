<x-correo titulo="Ya tienes cuenta en Nódico"
          resumen="Intentaste registrarte con una dirección que ya tiene cuenta.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, esta cuenta ya existe
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Alguien acaba de intentar crear una cuenta en Nódico con <strong>{{ $correo }}</strong>,
        que ya tiene una. No creamos nada nuevo ni cambiamos nada de la tuya.
    </p>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Si fuiste tú y se te olvidó que ya estabas registrado, entra con tu contraseña de
        siempre. Y si no la recuerdas, pide una nueva.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:#FFE124; border:2px solid #2E2D2C;">
                <a href="{{ $urlAcceso }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Entrar a mi cuenta
                </a>
            </td>
            <td style="width:12px;">&nbsp;</td>
            <td style="border:2px solid #2E2D2C;">
                <a href="{{ $urlOlvide }}"
                   style="display:inline-block; padding:14px 28px; font-size:16px; font-weight:700; color:#1A1918; text-decoration:none;">
                    Olvidé mi contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 28px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Si no fuiste tú, alguien escribió tu dirección por error o está comprobando si tienes
        cuenta con nosotros. Tu cuenta sigue intacta y no hace falta que hagas nada; si te
        preocupa, cambia tu contraseña.
    </p>

</x-correo>
