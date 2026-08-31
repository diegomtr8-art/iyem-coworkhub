<?php

namespace App\Console\Commands;

use App\Models\Ajuste;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fase 4.G.3 — renueva el token de larga duración de Instagram/Facebook.
 *
 * El token caduca a los ~60 días. Este comando lo intercambia por uno nuevo
 * (`fb_exchange_token`) mucho antes de que caduque —corre por cron cada semana—
 * y lo guarda en `ajustes`. **Si no puede, avisa**: es justo donde estos feeds
 * se rompen meses después sin que nadie se entere hasta que un cliente lo nota.
 */
class RenovarTokenInstagram extends Command
{
    protected $signature = 'nodico:renovar-token-instagram';

    protected $description = 'Renueva el token de larga duración de Instagram y avisa si falla.';

    public function handle(): int
    {
        $token    = Ajuste::obtener('instagram_token', config('services.instagram.token'));
        $appId    = config('services.instagram.app_id');
        $appSecret = config('services.instagram.app_secret');

        if (! $token || ! $appId || ! $appSecret) {
            // Sin credenciales completas no hay nada que renovar todavía. No es
            // un fallo que deba gritar por correo: solo se anota.
            $this->warn('Instagram: faltan token o credenciales de la app; no se renueva. Ver docs/INSTAGRAM.md.');

            return self::SUCCESS;
        }

        try {
            $respuesta = Http::timeout(10)->get('https://graph.facebook.com/v21.0/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $appId,
                'client_secret'     => $appSecret,
                'fb_exchange_token' => $token,
            ])->throw()->json();

            $nuevo = $respuesta['access_token'] ?? null;
            if (! $nuevo) {
                throw new \RuntimeException('La respuesta no trajo access_token.');
            }

            Ajuste::guardar('instagram_token', $nuevo, 'Token de larga duración de Instagram (renovado por cron).');
            Ajuste::guardar('instagram_token_renovado_en', now()->toDateTimeString());

            $this->info('Instagram: token renovado correctamente.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Instagram: no se pudo renovar el token.', ['error' => $e->getMessage()]);
            $this->avisarDelFallo($e->getMessage());
            $this->error('Instagram: la renovación del token falló. Se avisó por correo.');

            return self::FAILURE;
        }
    }

    private function avisarDelFallo(string $motivo): void
    {
        $destino = config('services.instagram.avisar_a');
        if (! $destino) {
            return;
        }

        try {
            Mail::raw(
                "No se pudo renovar el token de Instagram de Nódico.\n\nMotivo: {$motivo}\n\n"
                . "Mientras no se renueve, el feed cae a la reja curada. Revisa docs/INSTAGRAM.md "
                . "para regenerarlo a mano.",
                fn ($m) => $m->to($destino)->subject('⚠ Token de Instagram de Nódico por caducar'),
            );
        } catch (\Throwable $e) {
            Log::error('Instagram: además, no se pudo avisar del fallo por correo.', ['error' => $e->getMessage()]);
        }
    }
}
