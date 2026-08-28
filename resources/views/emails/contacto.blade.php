<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo contacto desde el sitio de Nódico</title>
</head>
<body style="margin:0;padding:24px;background:#F4F1EA;font-family:Arial,Helvetica,sans-serif;color:#2E2D2C;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
        <tr>
            <td style="background:#FFE124;padding:20px 24px;">
                <h1 style="margin:0;font-size:18px;color:#2E2D2C;">Nuevo mensaje desde el sitio web</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;line-height:1.5;">
                    <tr>
                        <td style="padding:6px 0;width:120px;color:#6b6b6b;">Nombre</td>
                        <td style="padding:6px 0;font-weight:bold;">{{ $contacto->nombre }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b6b6b;">E-mail</td>
                        <td style="padding:6px 0;"><a href="mailto:{{ $contacto->email }}" style="color:#2E2D2C;">{{ $contacto->email }}</a></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b6b6b;">Teléfono</td>
                        <td style="padding:6px 0;">{{ $contacto->telefono ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b6b6b;">Empresa</td>
                        <td style="padding:6px 0;">{{ $contacto->empresa ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#6b6b6b;">Asunto</td>
                        <td style="padding:6px 0;">{{ $contacto->asunto ?: '—' }}</td>
                    </tr>
                </table>

                <p style="margin:20px 0 6px;color:#6b6b6b;font-size:14px;">Comentarios</p>
                <div style="background:#F4F1EA;border-radius:8px;padding:16px;font-size:14px;line-height:1.6;white-space:pre-line;">{{ $contacto->comentarios }}</div>

                <p style="margin-top:24px;font-size:12px;color:#8a8a8a;">
                    Recibido el {{ $contacto->created_at?->format('d/m/Y H:i') }} h · IP {{ $contacto->ip ?: '—' }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
