<x-correo titulo="Intentos de acceso a tu cuenta — Nódico"
          resumen="Bloqueamos temporalmente los intentos de acceso a tu cuenta.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, hubo varios intentos fallidos
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Alguien falló la contraseña de tu cuenta de Nódico varias veces seguidas, así que
        pausamos los intentos durante {{ $minutos }} minuto{{ $minutos === 1 ? '' : 's' }}.
        <strong>Nadie ha entrado</strong>: tu contraseña sigue siendo la misma.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 24px; background-color:#F4F1EA; border:1px solid #E8E1D1;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#2E2D2C;">
                    <strong>Cuándo:</strong> {{ $cuando }}
                </p>
                <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#2E2D2C;">
                    <strong>Desde la IP:</strong> {{ $ip }}
                </p>
                <p style="margin:0; font-size:13px; line-height:1.6; color:#2E2D2C; word-break:break-word;">
                    <strong>Navegador:</strong> {{ $agente ?: 'sin identificar' }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        <strong>Si fuiste tú</strong>, espera esos minutos y vuelve a intentarlo. Si no
        recuerdas tu contraseña, pide una nueva desde la pantalla de acceso.
    </p>

    <p style="margin:0 0 28px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        <strong>Si no fuiste tú</strong>, alguien está probando contraseñas contra tu cuenta.
        Cambia la tuya en cuanto puedas y escríbenos a
        <a href="mailto:{{ config('nodico.contacto_email') }}" style="color:#2E2D2C;">{{ config('nodico.contacto_email') }}</a>.
    </p>

</x-correo>
