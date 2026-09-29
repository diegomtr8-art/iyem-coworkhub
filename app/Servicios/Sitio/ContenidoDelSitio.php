<?php

namespace App\Servicios\Sitio;

use App\Models\Ajuste;
use App\Models\User;
use App\Models\VersionContenidoSitio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

    /** Dónde vive el borrador de la vista previa, por sección. */
    public const SESION_VISTA_PREVIA = 'sitio.vista_previa';

    /**
     * Red de seguridad, no el mecanismo: la caché se invalida al guardar. La
     * hora cubre lo que no pasa por el modelo (un UPDATE a mano en la base, un
     * borrado masivo), que de otro modo quedaría servido para siempre.
     */
    private const VIGENCIA_SEGUNDOS = 3600;

    /** Versiones que se conservan por sección. Deshacer solo usa la última. */
    private const VERSIONES_POR_SECCION = 20;

    /** @var array<string, mixed>|null Filas crudas de `ajustes`, por clave. */
    private ?array $filas = null;

    /** @var array<string, array<string, mixed>> */
    private array $resueltas = [];

    /** @return array<string, mixed> */
    public function seccion(string $clave): array
    {
        // El borrador va fuera de la memoria: lo publicado se resuelve una vez,
        // la vista previa depende de cada petición.
        return $this->conBorrador($clave, $this->resueltas[$clave] ??= $this->resolver($clave));
    }

    public function valor(string $clave, string $campo): mixed
    {
        return $this->seccion($clave)[$campo] ?? null;
    }

    /** Si hay una fila guardada para la sección, o se está sirviendo el respaldo. */
    public function personalizada(string $clave): bool
    {
        return array_key_exists($clave, $this->filas());
    }

    /**
     * Guarda una sección y conserva el valor anterior para poder deshacer.
     *
     * Los campos que no vengan conservan su valor vigente, y la fila queda
     * siempre completa. Valida con las mismas reglas con las que luego se lee:
     * lo que entra por aquí es lo que se va a servir.
     *
     * @param  array<string, mixed>  $valor
     * @return bool Si cambió algo. Guardar lo mismo no crea una versión.
     *
     * @throws ValidationException
     */
    public function guardar(string $clave, array $valor, ?User $autor = null): bool
    {
        $datos = $this->validar($clave, $valor);

        return DB::transaction(function () use ($clave, $datos, $autor) {
            $anterior = Ajuste::where('clave', $clave)->lockForUpdate()->first();

            if ($anterior && $anterior->valor === $datos) {
                return false;
            }

            $this->versionar($clave, $anterior?->valor, $autor);
            Ajuste::guardar($clave, $datos);

            return true;
        });
    }

    /**
     * Vuelve la sección a como estaba antes del último cambio. Si antes no
     * había nada guardado, vuelve al respaldo.
     *
     * @return bool Si había algo que deshacer.
     */
    public function deshacer(string $clave): bool
    {
        CatalogoDelSitio::campos($clave);

        return DB::transaction(function () use ($clave) {
            $version = VersionContenidoSitio::where('clave', $clave)->latest('id')->lockForUpdate()->first();

            if (! $version) {
                return false;
            }

            if ($version->valor === null) {
                Ajuste::where('clave', $clave)->first()?->delete();
            } else {
                Ajuste::guardar($clave, $version->valor);
            }

            $version->delete();

            return true;
        });
    }

    /**
     * Borra lo guardado y vuelve a los valores originales. Queda como una
     * versión más, así que también se puede deshacer.
     *
     * @return bool Si había algo guardado.
     */
    public function restablecer(string $clave, ?User $autor = null): bool
    {
        CatalogoDelSitio::campos($clave);

        return DB::transaction(function () use ($clave, $autor) {
            $actual = Ajuste::where('clave', $clave)->lockForUpdate()->first();

            if (! $actual) {
                return false;
            }

            $this->versionar($clave, $actual->valor, $autor);
            $actual->delete();

            return true;
        });
    }

    /** Quién hizo el cambio que está vigente, y cuándo. */
    public function ultimoCambio(string $clave): ?VersionContenidoSitio
    {
        return VersionContenidoSitio::with('autor:id,name')->where('clave', $clave)->latest('id')->first();
    }

    /**
     * Guarda un borrador que solo ve quien puede editar el sitio, al abrir la
     * página con `?vista_previa=1`. No toca la base ni la caché.
     *
     * @param  array<string, mixed>  $valor
     *
     * @throws ValidationException
     */
    public function prepararVistaPrevia(string $clave, array $valor): void
    {
        session()->put(self::SESION_VISTA_PREVIA . ".{$clave}", $this->validar($clave, $valor));
    }

    /** Si esta petición está viendo borradores en lugar de lo publicado. */
    public function enVistaPrevia(): bool
    {
        return $this->vistaPreviaPermitida() && (bool) session(self::SESION_VISTA_PREVIA);
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

    /**
     * @param  array<string, mixed>  $valor
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validar(string $clave, array $valor): array
    {
        $campos = CatalogoDelSitio::campos($clave);

        return Validator::make(
            array_intersect_key($valor, $campos) + $this->resolver($clave),
            array_map(fn (array $campo) => $campo['reglas'], $campos),
            [],
            array_map(fn (array $campo) => mb_strtolower($campo['etiqueta']), $campos),
        )->validate();
    }

    private function versionar(string $clave, mixed $anterior, ?User $autor): void
    {
        VersionContenidoSitio::create([
            'clave'         => $clave,
            'valor'         => $anterior,
            'guardada_por'  => $autor?->id,
        ]);

        $sobrantes = VersionContenidoSitio::where('clave', $clave)
            ->latest('id')
            ->skip(self::VERSIONES_POR_SECCION)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($sobrantes->isNotEmpty()) {
            VersionContenidoSitio::whereIn('id', $sobrantes)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $resuelta
     * @return array<string, mixed>
     */
    private function conBorrador(string $clave, array $resuelta): array
    {
        if (! $this->vistaPreviaPermitida()) {
            return $resuelta;
        }

        $borrador = session(self::SESION_VISTA_PREVIA . ".{$clave}");

        return is_array($borrador) ? array_intersect_key($borrador, $resuelta) + $resuelta : $resuelta;
    }

    /**
     * Solo con el parámetro en la URL y con permiso: el borrador vive en la
     * sesión de quien edita, pero además tiene que pedirlo, para que navegar
     * por el sitio después de una vista previa enseñe lo publicado.
     */
    private function vistaPreviaPermitida(): bool
    {
        $peticion = request();

        if (! $peticion->boolean('vista_previa') || ! $peticion->hasSession()) {
            return false;
        }

        $usuario = $peticion->user();

        return $usuario !== null && Gate::forUser($usuario)->allows('gestionar-sitio');
    }

    /** @return array<string, mixed> */
    private function resolver(string $clave): array
    {
        $campos = CatalogoDelSitio::campos($clave);
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

    /** @param array<int, string> $campos */
    private function avisar(string $clave, string $motivo, array $campos): void
    {
        Log::warning("Contenido del sitio inválido en «{$clave}»: {$motivo}; se sirve el respaldo", [
            'clave'  => $clave,
            'campos' => $campos,
        ]);
    }
}
