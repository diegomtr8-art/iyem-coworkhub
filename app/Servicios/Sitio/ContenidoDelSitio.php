<?php

namespace App\Servicios\Sitio;

use App\Models\Ajuste;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Contenido editable del sitio público, resuelto con respaldo.
 *
 * Cada campo sale de la fila de `ajustes` si existe y cumple sus reglas, y si
 * no, del respaldo de `CatalogoDelSitio`. La resolución es **campo a campo**:
 * un teléfono mal guardado no se lleva por delante el correo y la dirección.
 *
 * Nada de lo que haya en la tabla puede tumbar la página pública. Una fila
 * corrupta, una base caída o una caché que no responde se registran en el log
 * y la portada se sirve con el respaldo.
 *
 * La portada la ve todo el mundo: todo el contenido se lee de una vez y se
 * cachea. La caché la invalida `Ajuste` al guardarse o borrarse, así que el
 * cambio se ve en la siguiente visita, sin esperar ni reiniciar nada. Las
 * escrituras masivas (`Ajuste::where(...)->delete()`) no disparan eventos del
 * modelo: tardan hasta una hora en verse.
 */
class ContenidoDelSitio
{
    public const CACHE = 'sitio:contenido';

    /**
     * Red de seguridad, no el mecanismo: la caché se invalida al guardar. La
     * hora cubre lo que no pasa por el modelo (un UPDATE a mano en la base, un
     * borrado masivo), que de otro modo quedaría servido para siempre.
     */
    private const VIGENCIA_SEGUNDOS = 3600;

    /** @var array<string, mixed>|null Filas crudas de `ajustes`, por clave. */
    private ?array $filas = null;

    /** @var array<string, array<string, mixed>> */
    private array $resueltas = [];

    /** @return array<string, mixed> */
    public function seccion(string $clave): array
    {
        return $this->resueltas[$clave] ??= $this->resolver($clave);
    }

    public function valor(string $clave, string $campo): mixed
    {
        return $this->seccion($clave)[$campo] ?? null;
    }

    /**
     * Guarda una sección. Los campos que no vengan conservan su valor vigente,
     * y la fila queda siempre completa. Valida con las mismas reglas con las
     * que luego se lee: lo que entra por aquí es lo que se va a servir.
     *
     * @param  array<string, mixed>  $valor
     *
     * @throws ValidationException
     */
    public function guardar(string $clave, array $valor): void
    {
        $campos = $this->campos($clave);

        $datos = Validator::make(
            array_intersect_key($valor, $campos) + $this->seccion($clave),
            array_map(fn (array $campo) => $campo['reglas'], $campos),
        )->validate();

        Ajuste::guardar($clave, $datos);
    }

    /** Lo llama `Ajuste` al cambiar una fila: la siguiente lectura va a la base. */
    public function olvidar(): void
    {
        $this->filas = null;
        $this->resueltas = [];

        try {
            Cache::forget(self::CACHE);
        } catch (\Throwable $e) {
            Log::error('No se pudo invalidar la caché del contenido del sitio', ['error' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function resolver(string $clave): array
    {
        $campos = $this->campos($clave);
        $guardado = $this->filas()[$clave] ?? null;

        $respaldo = fn (array $solo) => array_map(fn (array $campo) => ($campo['respaldo'])(), $solo);

        if ($guardado === null) {
            return $respaldo($campos);
        }

        if (! is_array($guardado)) {
            $this->avisar($clave, 'la fila no es un objeto', array_keys($campos));

            return $respaldo($campos);
        }

        // Solo se valida lo que está guardado: un campo que falta se completa
        // con su respaldo sin que eso sea un error (p. ej. un campo nuevo que
        // se añadió al catálogo después de guardar la sección).
        $presentes = array_intersect_key($campos, $guardado);
        $validador = Validator::make(
            array_intersect_key($guardado, $presentes),
            array_map(fn (array $campo) => $campo['reglas'], $presentes),
        );

        $invalidos = array_keys($validador->errors()->messages());

        if ($invalidos) {
            $this->avisar($clave, 'hay campos que no cumplen sus reglas', $invalidos);
        }

        $resultado = [];

        foreach ($campos as $nombre => $campo) {
            $resultado[$nombre] = array_key_exists($nombre, $guardado) && ! in_array($nombre, $invalidos, true)
                ? $guardado[$nombre]
                : ($campo['respaldo'])();
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    private function filas(): array
    {
        if ($this->filas !== null) {
            return $this->filas;
        }

        try {
            return $this->filas = Cache::remember(self::CACHE, self::VIGENCIA_SEGUNDOS, fn () => Ajuste::query()
                ->whereIn('clave', CatalogoDelSitio::claves())
                ->pluck('valor', 'clave')
                ->all());
        } catch (\Throwable $e) {
            // Sin base o sin caché, el sitio sale con el respaldo. No se guarda
            // el vacío en `$filas`: la próxima petición vuelve a intentarlo.
            Log::error('No se pudo leer el contenido del sitio; se sirve el respaldo', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** @return array<string, array{reglas: array<int, mixed>, respaldo: \Closure}> */
    private function campos(string $clave): array
    {
        return CatalogoDelSitio::secciones()[$clave]
            ?? throw new InvalidArgumentException("La sección «{$clave}» no está en el catálogo del sitio.");
    }

    /** @param array<int, string> $campos */
    private function avisar(string $clave, string $motivo, array $campos): void
    {
        Log::warning("Contenido del sitio inválido en «{$clave}»: {$motivo}; se sirve el respaldo", [
            'clave'  => $clave,
            'campos' => $campos,
        ]);
    }
}
