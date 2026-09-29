<?php

namespace App\Servicios\Sitio;

use Closure;

/**
 * Constructores de campos del catálogo del sitio. Cada tipo trae sus reglas y
 * su control en el panel, para que declarar un campo sea decir qué es, cuánto
 * cabe y cuál es el texto de hoy, no repetir reglas de validación.
 *
 * Los topes (`max`) son de diseño, no de base de datos: un título de 300
 * caracteres es texto válido y rompe la maquetación igual.
 */
final class Campo
{
    public static function texto(string $etiqueta, int $max, mixed $respaldo, ?string $ayuda = null, bool $requerido = true): array
    {
        return self::campo('texto', $etiqueta, $ayuda, [$requerido ? 'required' : 'nullable', 'string', "max:{$max}"], $respaldo);
    }

    /** Texto de varias líneas. Sigue siendo texto: nada de HTML. */
    public static function parrafo(string $etiqueta, int $max, mixed $respaldo, ?string $ayuda = null, bool $requerido = true): array
    {
        return self::campo('parrafo', $etiqueta, $ayuda, [$requerido ? 'required' : 'nullable', 'string', "max:{$max}"], $respaldo);
    }

    /**
     * Enlace a un sitio de fuera. Solo https y, si se dan, solo esos hosts: un
     * enlace roto o apuntando a otro lado en el pie está roto para todos.
     *
     * @param  array<int, string>|null  $hosts
     */
    public static function url(string $etiqueta, mixed $respaldo, ?string $ayuda = null, bool $requerido = true, ?array $hosts = null): array
    {
        $reglas = [$requerido ? 'required' : 'nullable', 'string', 'max:300', 'url:https'];

        if ($hosts) {
            $reglas[] = self::host($hosts);
        }

        return self::campo('url', $etiqueta, $ayuda, $reglas, $respaldo);
    }

    /** El id de 11 caracteres de un video de YouTube, nunca una URL ni un embed. */
    public static function youtube(string $etiqueta, mixed $respaldo, ?string $ayuda = null): array
    {
        return self::campo('youtube', $etiqueta, $ayuda, ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/'], $respaldo);
    }

    public static function numero(string $etiqueta, float $minimo, float $maximo, mixed $respaldo, ?string $ayuda = null): array
    {
        return self::campo('numero', $etiqueta, $ayuda, ['required', 'numeric', "between:{$minimo},{$maximo}"], $respaldo);
    }

    public static function booleano(string $etiqueta, bool $respaldo = true, ?string $ayuda = null): array
    {
        return self::campo('booleano', $etiqueta, $ayuda, ['required', 'boolean'], $respaldo);
    }

    /**
     * Una foto del sitio. Se guarda `{id, alt}`: `id` apunta a `imagenes_sitio`
     * (o es nulo, y entonces se usa la foto fija del respaldo) y `alt` es el
     * texto alternativo, obligatorio salvo en fotos decorativas.
     *
     * @param  array{src: string, srcset?: ?string, width: int, height: int, alt: string}  $fija
     */
    public static function imagen(string $etiqueta, string $formato, array $fija, ?string $ayuda = null, bool $decorativa = false): array
    {
        return [
            'etiqueta'   => $etiqueta,
            'tipo'       => 'imagen',
            'ayuda'      => $ayuda,
            'formato'    => $formato,
            'decorativa' => $decorativa,
            'fija'       => $fija,
            'reglas'     => ['required', 'array', self::reglaImagen($formato, $decorativa)],
            'respaldo'   => fn () => ['id' => null, 'alt' => $decorativa ? '' : $fija['alt']],
        ];
    }

    /**
     * Una lista de elementos con los mismos campos (servicios, valores…).
     * `minimo` y `maximo` salen de la maquetación: fuera de ese rango, la
     * retícula se rompe. Con los dos iguales, la lista es de tamaño fijo.
     *
     * @param  array<string, array>  $campos
     * @param  array<int, array<string, mixed>>  $respaldo
     */
    public static function lista(string $etiqueta, array $campos, int $minimo, int $maximo, array $respaldo, ?string $ayuda = null, ?string $elemento = null, int $multiplo = 1): array
    {
        $reglas = ['required', 'array', "min:{$minimo}", "max:{$maximo}"];

        // Retículas de 2 o 3 columnas: con un elemento suelto queda un hueco.
        if ($multiplo > 1) {
            $reglas[] = function (string $atributo, mixed $valor, Closure $falla) use ($multiplo): void {
                if (is_array($valor) && count($valor) % $multiplo !== 0) {
                    $falla("Tienen que ser múltiplos de {$multiplo}: con otro número la retícula queda con un hueco.");
                }
            };
        }

        return [
            'etiqueta' => $etiqueta,
            'tipo'     => 'lista',
            'ayuda'    => $ayuda,
            'elemento' => $elemento ?? 'Elemento',
            'campos'   => $campos,
            'minimo'   => $minimo,
            'maximo'   => $maximo,
            'multiplo' => $multiplo,
            'reglas'   => $reglas,
            'respaldo' => fn () => $respaldo,
        ];
    }

    /**
     * Reglas listas para el validador: las de cada campo y, en las listas, las
     * de cada elemento con comodín (`servicios.*.titulo`).
     *
     * @param  array<string, array>  $campos
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(array $campos): array
    {
        $reglas = [];

        foreach ($campos as $nombre => $campo) {
            $reglas[$nombre] = $campo['reglas'];

            if ($campo['tipo'] === 'lista') {
                foreach ($campo['campos'] as $sub => $definicion) {
                    $reglas["{$nombre}.*.{$sub}"] = $definicion['reglas'];
                }
            }
        }

        return $reglas;
    }

    /**
     * Nombres legibles para los mensajes de error.
     *
     * @param  array<string, array>  $campos
     * @return array<string, string>
     */
    public static function nombres(array $campos): array
    {
        $nombres = [];

        foreach ($campos as $nombre => $campo) {
            $nombres[$nombre] = mb_strtolower($campo['etiqueta']);

            if ($campo['tipo'] === 'lista') {
                foreach ($campo['campos'] as $sub => $definicion) {
                    $nombres["{$nombre}.*.{$sub}"] = mb_strtolower($definicion['etiqueta']);
                }
            }
        }

        return $nombres;
    }

    /**
     * Deja solo lo que el catálogo conoce, con su forma: nada de claves de más
     * dentro de un elemento de lista o de una imagen, aunque la petición las
     * traiga. Lo que se guarda es exactamente lo que se valida.
     *
     * @param  array<string, array>  $campos
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function limpiar(array $campos, array $datos): array
    {
        $limpio = [];

        foreach ($campos as $nombre => $campo) {
            if (! array_key_exists($nombre, $datos)) {
                continue;
            }

            $valor = $datos[$nombre];

            $limpio[$nombre] = match ($campo['tipo']) {
                'lista'    => array_values(array_map(
                    fn ($elemento) => self::limpiar($campo['campos'], (array) $elemento),
                    (array) $valor,
                )),
                'imagen'   => ['id' => isset($valor['id']) ? (int) $valor['id'] : null, 'alt' => (string) ($valor['alt'] ?? '')],
                'booleano' => (bool) $valor,
                'numero'   => $valor + 0,
                default    => $valor,
            };
        }

        return $limpio;
    }

    /**
     * Para los pocos campos con reglas propias (correo, teléfono, usuario de
     * Instagram): `tipo` elige el control del panel.
     *
     * @param  array<int, mixed>  $reglas
     */
    public static function campoConReglas(string $tipo, string $etiqueta, ?string $ayuda, array $reglas, mixed $respaldo): array
    {
        return self::campo($tipo, $etiqueta, $ayuda, $reglas, $respaldo);
    }

    /** @param array<int, mixed> $reglas */
    private static function campo(string $tipo, string $etiqueta, ?string $ayuda, array $reglas, mixed $respaldo): array
    {
        return [
            'etiqueta' => $etiqueta,
            'tipo'     => $tipo,
            'ayuda'    => $ayuda,
            'reglas'   => $reglas,
            'respaldo' => $respaldo instanceof Closure ? $respaldo : fn () => $respaldo,
        ];
    }

    /** @param array<int, string> $hosts */
    public static function host(array $hosts): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) use ($hosts): void {
            if (! is_string($valor)) {
                return; // ya lo rechaza la regla `string`
            }

            $host = strtolower((string) parse_url($valor, PHP_URL_HOST));

            if (! in_array($host, $hosts, true)) {
                $falla('El enlace tiene que ser de ' . $hosts[0] . '.');
            }
        };
    }

    private static function reglaImagen(string $formato, bool $decorativa): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) use ($formato, $decorativa): void {
            if (! is_array($valor)) {
                return;
            }

            $id = $valor['id'] ?? null;
            $alt = $valor['alt'] ?? '';

            if ($id !== null && ! (is_int($id) || ctype_digit((string) $id))) {
                $falla('La foto no es válida.');

                return;
            }

            if ($id !== null && app(ContenidoDelSitio::class)->imagen((int) $id)?->formato !== $formato) {
                $falla('Esa foto no existe o no es del tamaño que pide este lugar.');

                return;
            }

            if (! is_string($alt) || mb_strlen($alt) > 150) {
                $falla('El texto alternativo tiene que ser texto de 150 caracteres como mucho.');

                return;
            }

            if (! $decorativa && trim($alt) === '') {
                $falla('Describe la foto: quien usa lector de pantalla depende de ese texto.');
            }
        };
    }
}
