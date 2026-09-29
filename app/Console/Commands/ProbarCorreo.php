<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Comprueba que el correo saliente de verdad sale.
 *
 * Sin correo no hay verificación de cuenta ni recuperación de contraseña, así
 * que el registro queda inservible desde el primer minuto. Este comando existe
 * para no descubrirlo cuando ya hay gente intentando entrar.
 *
 * Primero enseña la configuración vigente, porque el error más común no es de
 * red sino de caché: `config:cache` congela el `.env` viejo y uno pasa media
 * hora depurando credenciales que ya estaban bien.
 *
 *   php artisan nodico:probar-correo
 *   php artisan nodico:probar-correo alguien@ejemplo.com
 */
class ProbarCorreo extends Command
{
    protected $signature = 'nodico:probar-correo {destino? : A quién mandarlo. Por omisión, el remitente configurado.}';

    protected $description = 'Manda un correo de prueba y explica el fallo si no sale.';

    public function handle(): int
    {
        $remitente = config('mail.from.address');
        $destino = $this->argument('destino') ?: $remitente;
        $transporte = config('mail.default');

        $this->newLine();
        $this->line('  <fg=gray>Configuración vigente</>');
        $this->table(['Ajuste', 'Valor'], [
            ['Transporte', $transporte],
            ['Servidor', config("mail.mailers.{$transporte}.host").':'.config("mail.mailers.{$transporte}.port")],
            ['Usuario', config("mail.mailers.{$transporte}.username") ?: '(ninguno)'],
            ['Contraseña', config("mail.mailers.{$transporte}.password") ? '(puesta)' : '(VACÍA)'],
            ['Remitente', $remitente],
            ['Destino', $destino],
        ]);

        if ($transporte === 'log') {
            $this->newLine();
            $this->warn('  El transporte es "log": los correos se escriben en');
            $this->warn('  storage/logs/laravel.log y NUNCA salen a internet.');
            $this->warn('  Pon MAIL_MAILER=smtp en el .env.');
            $this->newLine();

            return self::FAILURE;
        }

        if ($transporte === 'smtp' && blank(config('mail.mailers.smtp.password'))) {
            $this->newLine();
            $this->error('  MAIL_PASSWORD está vacía. Gmail va a rechazar la conexión.');
            $this->line('  Ejecuta configurar-correo.bat, o ponla a mano en el .env.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line("  Enviando a <options=bold>{$destino}</>...");

        try {
            Mail::raw($this->cuerpo($transporte), function ($mensaje) use ($destino) {
                $mensaje->to($destino)->subject('Prueba de correo · Nódico');
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('  No se pudo enviar.');
            $this->newLine();
            $this->line('  <fg=gray>'.$e->getMessage().'</>');
            $this->newLine();
            $this->explicar($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("  Enviado. Revisa la bandeja de {$destino}.");
        $this->line('  <fg=gray>Si no aparece, mira en Spam: el primero suele caer ahí.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function cuerpo(string $transporte): string
    {
        return implode("\n", [
            'Si estás leyendo esto, el correo de Nódico funciona.',
            '',
            'Remitente: '.config('mail.from.address'),
            'Servidor:  '.config("mail.mailers.{$transporte}.host").':'.config("mail.mailers.{$transporte}.port"),
            'Entorno:   '.app()->environment(),
            'Sitio:     '.config('app.url'),
            'Fecha:     '.now()->format('d/m/Y H:i:s'),
        ]);
    }

    /**
     * Traduce los fallos habituales de SMTP a algo accionable.
     */
    private function explicar(string $error): void
    {
        $pistas = [
            'Username and Password not accepted' => [
                'Gmail rechazó las credenciales.',
                'Casi siempre es que se puso la contraseña normal de la cuenta.',
                'Hace falta una contraseña de aplicación de 16 caracteres:',
                '  https://myaccount.google.com/apppasswords',
                '(y la cuenta necesita verificación en dos pasos para poder crearla)',
            ],
            'Connection could not be established' => [
                'No se alcanzó el servidor: algo bloquea el puerto.',
                'Puede ser el antivirus, el firewall o la red del hosting.',
                'Prueba el otro puerto:  MAIL_PORT=465  con  MAIL_SCHEME=smtps',
            ],
            'Connection timed out' => [
                'La conexión expiró: el puerto está bloqueado de salida.',
                'En hosting compartido esto es común. Prueba MAIL_PORT=465',
                'con MAIL_SCHEME=smtps, y si tampoco, pide al hosting que',
                'abra el SMTP saliente hacia smtp.gmail.com.',
            ],
            'could not find driver' => [
                'Falta una extensión de PHP. Habilita openssl en el php.ini.',
            ],
            'SSL' => [
                'Problema de certificado TLS.',
                'Comprueba que el puerto y el esquema concuerden:',
                '  587 → MAIL_SCHEME vacío (STARTTLS automático)',
                '  465 → MAIL_SCHEME=smtps',
                'Ojo: este proyecto usa MAIL_SCHEME. MAIL_ENCRYPTION ya no se lee.',
            ],
        ];

        foreach ($pistas as $aguja => $lineas) {
            if (str_contains($error, $aguja)) {
                $this->line('  <fg=yellow>Qué significa</>');
                foreach ($lineas as $linea) {
                    $this->line('  '.$linea);
                }
                $this->newLine();

                return;
            }
        }

        $this->line('  <fg=gray>Si la configuración de arriba se ve bien pero no coincide con</>');
        $this->line('  <fg=gray>el .env, corre "php artisan config:clear": la caché está vieja.</>');
        $this->newLine();
    }
}
