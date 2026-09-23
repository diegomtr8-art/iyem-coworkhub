<?php

namespace App\Notifications\Canales;

use App\Models\DispositivoPush;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Canal de notificaciones push de la app, vía el servicio de Expo.
 *
 * Una notificación lo usa añadiendo `ExpoPush::class` a su `via()` y un método
 * `toExpoPush($notifiable)` que devuelva `['titulo', 'cuerpo', 'datos']`. Llega
 * a todos los teléfonos con sesión abierta de esa persona. Si Expo dice que un
 * token ya no existe (la app se desinstaló), se borra para no insistir.
 *
 * Un fallo aquí nunca tumba la petición que originó el aviso: se registra y ya.
 */
class ExpoPush
{
    private const URL = 'https://exp.host/--/api/v2/push/send';

    public function send(object $notifiable, Notification $notificacion): void
    {
        if (! method_exists($notificacion, 'toExpoPush') || ! method_exists($notifiable, 'dispositivosPush')) {
            return;
        }

        $dispositivos = $notifiable->dispositivosPush()->get();

        if ($dispositivos->isEmpty()) {
            return;
        }

        $mensaje = $notificacion->toExpoPush($notifiable);

        $envios = $dispositivos->map(fn (DispositivoPush $d) => [
            'to'    => $d->expo_push_token,
            'title' => $mensaje['titulo'],
            'body'  => $mensaje['cuerpo'],
            'data'  => $mensaje['datos'] ?? [],
            'sound' => 'default',
        ])->values()->all();

        try {
            $respuesta = Http::timeout(10)->acceptJson()->post(self::URL, $envios);
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el aviso push.', ['motivo' => $e->getMessage()]);

            return;
        }

        foreach ((array) $respuesta->json('data', []) as $i => $resultado) {
            if (($resultado['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($dispositivos[$i])) {
                $dispositivos[$i]->delete();
            }
        }
    }
}
