<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * E — Los documentos legales y, sobre todo, **su versión**.
 *
 * La versión vive en la cabecera del propio `.md`, no en un `config` aparte, a
 * propósito: quien edite el texto tiene la línea de versión delante y no puede
 * cambiar el contenido sin verla. Con la versión en otro archivo, tarde o
 * temprano alguien actualiza el aviso de privacidad y nadie bumpea nada, y
 * todas las constancias guardadas pasan a apuntar a un texto que ya no existe.
 *
 * Por eso se guarda la versión aceptada y no un simple sí/no: cuando el IYEM
 * publique una versión nueva, a quien tenga aceptada la anterior se le vuelve a
 * pedir.
 */
class DocumentosLegales
{
    /** Clave interna => archivo en `resources/legal`, y nombre de la ruta pública. */
    public const DOCUMENTOS = [
        'privacidad' => ['archivo' => 'aviso-de-privacidad', 'ruta' => 'privacidad'],
        'terminos'   => ['archivo' => 'terminos',            'ruta' => 'terminos'],
    ];

    /** @var array<string, array<string, mixed>> */
    private array $memoria = [];

    /**
     * @return array{titulo: string, descripcion: string, version: string, provisional: bool, contenido: string}
     */
    public function leer(string $clave): array
    {
        if (isset($this->memoria[$clave])) {
            return $this->memoria[$clave];
        }

        $archivo = self::DOCUMENTOS[$clave]['archivo'] ?? null;

        abort_if($archivo === null, 404);

        $ruta = resource_path("legal/{$archivo}.md");

        abort_unless(is_file($ruta), 404);

        $crudo = (string) file_get_contents($ruta);
        $meta  = [];

        // Cabecera sencilla `clave: valor` al inicio del archivo.
        if (preg_match('/^---\R(.*?)\R---\R(.*)$/s', $crudo, $m)) {
            foreach (preg_split('/\R/', $m[1]) as $linea) {
                if (str_contains($linea, ':')) {
                    [$k, $v] = explode(':', $linea, 2);
                    $meta[trim($k)] = trim($v);
                }
            }
            $crudo = $m[2];
        }

        return $this->memoria[$clave] = [
            'titulo'      => $meta['titulo'] ?? Str::headline($archivo),
            'descripcion' => $meta['descripcion'] ?? '',
            // Sin version explicita se usa `0`, que no coincidira con ninguna
            // constancia y obligara a aceptar de nuevo. Fallar hacia pedir el
            // consentimiento es el lado seguro.
            'version'     => $meta['version'] ?? '0',
            'provisional' => ($meta['provisional'] ?? 'false') === 'true',
            'contenido'   => Str::markdown($crudo),
        ];
    }

    public function version(string $clave): string
    {
        return $this->leer($clave)['version'];
    }

    /**
     * Versión vigente de cada documento.
     *
     * @return array<string, string>
     */
    public function versiones(): array
    {
        $versiones = [];

        foreach (array_keys(self::DOCUMENTOS) as $clave) {
            $versiones[$clave] = $this->version($clave);
        }

        return $versiones;
    }

    /**
     * Etiqueta para enseñar y para la constancia.
     *
     * Si los dos documentos van a la misma versión se muestra una sola; si
     * divergen —que pasará en cuanto el IYEM revise uno y no el otro— se
     * muestran las dos, porque decir «versión 1.0» cuando los términos van por
     * la 1.1 seria falso en un documento que alguien esta aceptando.
     */
    public function etiquetaDeVersion(): string
    {
        $versiones = $this->versiones();

        if (count(array_unique($versiones)) === 1) {
            return (string) reset($versiones);
        }

        $partes = [];

        foreach ($versiones as $clave => $version) {
            $partes[] = ($clave === 'privacidad' ? 'privacidad' : 'términos') . ' ' . $version;
        }

        return implode(' · ', $partes);
    }

    /**
     * BE-04 — ¿alguno sigue sin validación jurídica del IYEM?
     *
     * Mientras esto sea `true`, lo que se está recogiendo son constancias sobre
     * un texto que nadie ha aprobado. El mecanismo funciona; la validez legal
     * no depende de él.
     */
    public function algunoEsProvisional(): bool
    {
        foreach (array_keys(self::DOCUMENTOS) as $clave) {
            if ($this->leer($clave)['provisional']) {
                return true;
            }
        }

        return false;
    }
}
