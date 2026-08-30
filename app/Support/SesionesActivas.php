<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * B — Sesiones abiertas de una persona, para que pueda verlas y cerrarlas.
 *
 * Se apoya en la tabla `sessions`, que solo existe con el driver `database`.
 * Si algún día se cambia a `file` o `cookie` esta pantalla no puede inventarse
 * los datos, así que lo dice en vez de mostrar una lista vacía y engañosa.
 */
class SesionesActivas
{
    public function disponible(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listar(User $usuario, ?string $sesionActual): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        return collect(
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $usuario->id)
                ->orderByDesc('last_activity')
                ->get()
        )->map(fn ($fila) => [
            // **Nunca el identificador de sesion real.** Ver `referencia()`.
            'ref'         => $this->referencia((string) $fila->id),
            'esActual'    => $fila->id === $sesionActual,
            'ip'          => $fila->ip_address,
            'dispositivo' => $this->describirDispositivo((string) $fila->user_agent),
            'agente'      => Str::limit((string) $fila->user_agent, 120),
            'ultimaVez'   => now()->createFromTimestamp($fila->last_activity)->diffForHumans(),
        ]);
    }

    /** Cierra todas menos la actual. Devuelve cuántas se cerraron. */
    public function cerrarOtras(User $usuario, ?string $sesionActual): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->when($sesionActual, fn ($consulta) => $consulta->where('id', '!=', $sesionActual))
            ->delete();
    }

    /**
     * Cierra una sesion a partir de su **referencia**, no de su identificador.
     *
     * Se recorren las sesiones de esta persona y se compara con `hash_equals`.
     * El `where` por `user_id` sigue estando: sin el, cualquiera podria cerrar
     * la sesion de otra persona.
     */
    public function cerrar(User $usuario, string $referencia): bool
    {
        if (! $this->disponible()) {
            return false;
        }

        $filas = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->get(['id']);

        foreach ($filas as $fila) {
            if (hash_equals($this->referencia((string) $fila->id), $referencia)) {
                return DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $usuario->id)
                    ->where('id', $fila->id)
                    ->delete() > 0;
            }
        }

        return false;
    }

    /**
     * Identificador opaco de una sesion, para enviarlo al navegador.
     *
     * **El identificador de sesion es una credencial**, igual que una
     * contrasena: quien lo tenga es esa sesion. Antes se mandaba en claro a la
     * pantalla y viajaba dentro de la URL del boton de cerrar
     * (`DELETE /seguridad/sesiones/<id>`), asi que quedaba escrito en el
     * historial del navegador y en el log de accesos de Apache — que en este
     * hosting comparte carpeta con la aplicacion.
     *
     * La referencia es un HMAC con `APP_KEY`: sirve para senalar cual cerrar y
     * no vale para nada mas. No es reversible y no se puede calcular sin la
     * clave de la aplicacion.
     */
    private function referencia(string $idDeSesion): string
    {
        return hash_hmac('sha256', $idDeSesion, (string) config('app.key'));
    }

    /**
     * Descripción legible del agente de usuario.
     *
     * Deliberadamente tosca: la idea es que alguien reconozca «ah, ese es mi
     * teléfono», no identificar el navegador con precisión. El agente completo
     * se muestra aparte para quien quiera mirarlo.
     */
    private function describirDispositivo(string $agente): string
    {
        $sistema = match (true) {
            Str::contains($agente, 'iPhone')           => 'iPhone',
            Str::contains($agente, 'iPad')             => 'iPad',
            Str::contains($agente, 'Android')          => 'Android',
            Str::contains($agente, ['Windows NT'])     => 'Windows',
            Str::contains($agente, ['Macintosh', 'Mac OS X']) => 'Mac',
            Str::contains($agente, 'Linux')            => 'Linux',
            default                                    => 'Dispositivo desconocido',
        };

        // Chrome se anuncia como Safari, y Edge como los dos: el orden importa.
        $navegador = match (true) {
            Str::contains($agente, 'Edg/')     => 'Edge',
            Str::contains($agente, 'OPR/')     => 'Opera',
            Str::contains($agente, 'Chrome/')  => 'Chrome',
            Str::contains($agente, 'Firefox/') => 'Firefox',
            Str::contains($agente, 'Safari/')  => 'Safari',
            default                            => null,
        };

        return $navegador ? "{$sistema} · {$navegador}" : $sistema;
    }
}
