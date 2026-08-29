{{--
    Plantilla base de los correos de acceso de Nódico.

    HTML de correo, no HTML de web: tablas, estilos en línea y nada de flexbox,
    grid ni hojas externas. Outlook y Gmail descartan buena parte del CSS
    moderno, y un correo de verificación que se ve roto es un correo que no se
    pulsa.

    Los colores son los del sistema: `tinta` #1A1918, `nodo-400` #FFE124,
    `cream` #F4F1EA. Sobre el amarillo siempre va texto oscuro — blanco sobre
    #FFE124 da 1.31:1 y está prohibido en este sistema.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $titulo ?? 'Nódico' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F1EA; font-family:'Helvetica Neue',Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">

    {{-- Resumen que asoma en la bandeja junto al asunto. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $resumen ?? '' }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F1EA;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border:2px solid #2E2D2C;">

                    {{-- Cabecera --}}
                    <tr>
                        <td style="background-color:#1A1918; padding:28px 32px;">
                            <p style="margin:0; font-family:'Courier New',Courier,monospace; font-size:11px; letter-spacing:0.18em; text-transform:uppercase; color:#FFE124;">
                                Nódico
                            </p>
                            <p style="margin:6px 0 0; font-size:13px; line-height:1.5; color:#ffffff; opacity:0.75;">
                                Coworking del Instituto Yucateco de Emprendedores
                            </p>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:36px 32px 8px;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:24px 32px 32px; border-top:1px solid #E8E1D1;">
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#2E2D2C; opacity:0.75;">
                                Nódico · {{ config('nodico.direccion_corta') }}<br>
                                <a href="mailto:{{ config('nodico.contacto_email') }}" style="color:#2E2D2C;">{{ config('nodico.contacto_email') }}</a>
                                · {{ config('nodico.telefono') }}
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="max-width:560px; margin:20px auto 0; font-size:12px; line-height:1.6; color:#2E2D2C; opacity:0.7; text-align:center;">
                    Nódico nunca te pedirá tu contraseña por correo ni por teléfono.
                </p>

            </td>
        </tr>
    </table>

</body>
</html>
