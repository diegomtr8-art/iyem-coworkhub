<?php

namespace App\Servicios\Instagram;

use App\Models\Ajuste;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4.G.3 — el feed de Instagram de @nodicomx.
 *
 * Reglas que hacen que esto no se rompa en producción:
 *  - **Nunca se llama a la API desde el navegador**: expondría el token. Se
 *    llama aquí, en el servidor, y se sirve el resultado ya masticado.
 *  - **Se cachea** (una hora por defecto): la portada no dispara una llamada a
 *    Meta por cada visita.
 *  - **Si la API falla o no hay token, cae a la reja curada** de la tabla
 *    `ajustes`. La sección nunca queda vacía ni muestra un error de Meta.
 */
class FeedDeInstagram
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    /**
     * @return array{perfil: ?array, publicaciones: array<int, array>, fuente: string}
     */
    public function obtener(): array
    {
        // El token vigente lo puede haber renovado el comando y guardado en
        // `ajustes`; si no, se usa el de arranque del .env.
        $token  = Ajuste::obtener('instagram_token', config('services.instagram.token'));
        $userId = config('services.instagram.user_id');

        // Sin credenciales: directo a la reja curada, sin tocar red ni caché.
        if (! $token || ! $userId) {
            return $this->curado();
        }

        $minutos = (int) config('services.instagram.cache_min', 60);

        return Cache::remember('instagram_feed', now()->addMinutes($minutos), function () use ($token, $userId) {
            try {
                return $this->desdeLaApi($token, $userId);
            } catch (\Throwable $e) {
                Log::warning('Feed de Instagram: la API falló, se usa la reja curada.', ['error' => $e->getMessage()]);

                return $this->curado();
            }
        });
    }

    /**
     * @return array{perfil: array, publicaciones: array<int, array>, fuente: string}
     */
    private function desdeLaApi(string $token, string $userId): array
    {
        $perfil = Http::timeout(8)->get(self::GRAPH . "/{$userId}", [
            'fields'       => 'username,profile_picture_url,followers_count,media_count',
            'access_token' => $token,
        ])->throw()->json();

        $media = Http::timeout(8)->get(self::GRAPH . "/{$userId}/media", [
            'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url',
            'access_token' => $token,
            'limit'        => 12,
        ])->throw()->json('data', []);

        $publicaciones = collect($media)->map(fn ($m) => [
            'id'        => $m['id'] ?? null,
            'permalink' => $m['permalink'] ?? null,
            // Los videos no dan media_url servible como imagen; se usa la miniatura.
            'imagen'    => ($m['media_type'] ?? '') === 'VIDEO'
                ? ($m['thumbnail_url'] ?? null)
                : ($m['media_url'] ?? null),
            'tipo'      => strtolower($m['media_type'] ?? 'image'), // image | video | carousel_album
            'caption'   => $m['caption'] ?? null,
        ])->filter(fn ($p) => $p['permalink'])->values()->all();

        return [
            'perfil' => [
                'usuario'      => $perfil['username'] ?? 'nodicomx',
                'foto'         => $perfil['profile_picture_url'] ?? null,
                'seguidores'   => $perfil['followers_count'] ?? null,
                'publicaciones'=> $perfil['media_count'] ?? null,
            ],
            'publicaciones' => $publicaciones,
            'fuente'        => 'api',
        ];
    }

    /**
     * La reja curada de siempre: permalinks guardados a mano en `ajustes`.
     *
     * @return array{perfil: null, publicaciones: array<int, array>, fuente: string}
     */
    private function curado(): array
    {
        $permalinks = Ajuste::obtener('instagram_posts', []);

        $publicaciones = collect(is_array($permalinks) ? $permalinks : [])
            ->map(fn ($url) => ['id' => null, 'permalink' => $url, 'imagen' => null, 'tipo' => 'image', 'caption' => null])
            ->all();

        return ['perfil' => null, 'publicaciones' => $publicaciones, 'fuente' => 'curado'];
    }
}
