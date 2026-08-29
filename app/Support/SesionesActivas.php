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
            'id'          => $fila->id,
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

    public function cerrar(User $usuario, string $sesion): bool
    {
        if (! $this->disponible()) {
            return false;
        }

        // El `where` por `user_id` no es decorativo: sin él, cualquiera podría
        // cerrar la sesión de otra persona pasando su identificador.
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', $sesion)
            ->delete() > 0;
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
