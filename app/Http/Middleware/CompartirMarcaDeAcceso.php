<?php

namespace App\Http\Middleware;

use App\Models\Evento;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * F — Prueba social del panel de marca de las pantallas de acceso.
 *
 * Las cifras **salen de la base de datos**, no de un texto suelto en el
 * componente: es la misma regla que ya rige en el sitio público, y evita que
 * dentro de un año el panel siga presumiendo de un número que dejó de ser
 * cierto.
 *
 * Un dato en cero no se muestra. Anunciar «0 talleres este mes» es peor que no
 * anunciar nada, y en un espacio recién abierto pasaría a menudo.
 *
 * Va como middleware de las rutas de acceso y no en `HandleInertiaRequests`
 * para no pagar dos consultas en cada petición del resto del sitio.
 */
class CompartirMarcaDeAcceso
{
    /** Por debajo de esto, el dato de comunidad no se muestra. */
    private const MINIMO_MIEMBROS = 10;

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('marca', fn () => [
            'datos' => $this->datos(),
        ]);

        return $next($request);
    }

    /**
     * @return array<int, array{valor: string, texto: string}>
     */
    private function datos(): array
    {
        // Una hora de caché: son cifras de escaparate, no un panel de control.
        return Cache::remember('acceso:prueba-social', 3600, function (): array {
            $datos = [];

            $miembros = User::miembros()->count();

            // Piso deliberado. La cifra es real, pero «1 persona en la
            // comunidad» en la pantalla de acceso de un coworking dice lo
            // contrario de lo que pretende. Por debajo de este numero no se
            // ensena nada: mejor callar que presumir de vacio.
            if ($miembros >= self::MINIMO_MIEMBROS) {
                $datos[] = [
                    'valor' => $miembros >= 100 ? floor($miembros / 10) * 10 . '+' : (string) $miembros,
                    'texto' => 'personas en la comunidad',
                ];
            }

            $talleres = Evento::activos()
                ->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();

            if ($talleres > 0) {
                $datos[] = [
                    'valor' => (string) $talleres,
                    'texto' => $talleres === 1 ? 'taller este mes' : 'talleres este mes',
                ];
            }

            // Dato fijo y verificable, ya publicado en el sitio: no es una
            // promesa de marketing, es el aforo del espacio.
            $datos[] = ['valor' => '70', 'texto' => 'lugares en el coworking'];

            return $datos;
        });
    }
}
