<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * Adelanta el descubrimiento de los módulos de la página.
 *
 * `@vite` solo declara la entrada `app.js`. El chunk de la página concreta
 * (`Welcome.vue`, `Nosotros.vue`…) se importa dinámicamente desde dentro de
 * `app.js`, así que el navegador no se entera de que existe hasta que ese
 * archivo ha bajado y ejecutado. Medido en la portada, eso costaba un segundo
 * viaje completo: `app.js` de 1326 a 1838 ms y solo entonces los chunks de la
 * página, de 1956 a 2305 ms.
 *
 * Como Inertia entrega `$page` a la vista raíz, la plantilla sí sabe qué
 * componente va a montarse y puede declarar sus módulos desde el principio,
 * para que bajen en paralelo con la entrada.
 */
class PrecargaVite
{
    /**
     * URLs de los módulos que hacen falta para pintar el componente indicado.
     *
     * @return list<string>
     */
    public static function modulos(?string $componente): array
    {
        if (! $componente) {
            return [];
        }

        $manifiesto = static::manifiesto();
        $clave = "resources/js/Pages/{$componente}.vue";

        if (! isset($manifiesto[$clave])) {
            return [];
        }

        // La entrada ya la declara `@vite`; declararla otra vez no aporta nada.
        $entrada = 'resources/js/app.js';

        $claves = array_values(array_diff(
            [$clave, ...($manifiesto[$clave]['imports'] ?? [])],
            [$entrada]
        ));

        $urls = [];

        foreach ($claves as $k) {
            // `Vite::asset()` resuelve claves del manifiesto, no rutas compiladas.
            if (isset($manifiesto[$k]['file'])) {
                $urls[] = Vite::asset($k);
            }
        }

        return $urls;
    }

    /** @return array<string, array<string, mixed>> */
    protected static function manifiesto(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $ruta = public_path('build/manifest.json');

        if (! is_file($ruta)) {
            return $cache = [];
        }

        return $cache = json_decode(file_get_contents($ruta), true) ?: [];
    }
}
