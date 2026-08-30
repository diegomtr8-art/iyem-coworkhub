<x-correo titulo="Se solicitó cambiar el correo de tu cuenta — Nódico"
          resumen="Alguien pidió cambiar el correo de tu cuenta de Nódico.">

    <h1 style="margin:0 0 16px; font-size:26px; line-height:1.2; color:#1A1918; font-weight:700;">
        {{ $nombre }}, se pidió cambiar tu correo
    </h1>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        Se solicitó cambiar el correo de tu cuenta de Nódico a
        <strong>{{ $correoNuevo }}</strong>. El cambio no se aplica hasta que esa
        dirección se confirme.
    </p>

    <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#2E2D2C;">
        <strong>Si fuiste tú</strong>, no tienes que hacer nada aquí: revisa la bandeja
        de tu correo nuevo y confirma desde ahí.
    </p>

    <div style="margin:0 0 24px; padding:16px 20px; background-color:#FBEDED; border:2px solid #C0392B;">
        <p style="margin:0; font-size:15px; line-height:1.6; color:#7B241C;">
            <strong>Si no fuiste tú</strong>, entra a Nódico ahora mismo, cambia tu
            contraseña y cancela la solicitud desde «Mi seguridad». Mientras no se
            confirme, tu correo sigue siendo este.
        </p>
    </div>

    <p style="margin:0 0 28px; font-size:14px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
        Este mensaje se envía siempre a tu dirección actual, justamente para que puedas
        reaccionar si el cambio no lo pediste tú.
    </p>

</x-correo>
