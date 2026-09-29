<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un juego de archivos de imagen subido desde el módulo «Página Web»
 * (docs/CMS-PAGINA-WEB.md). Vive en el disco `medios`, fuera de todo lo que
 * sincroniza el despliegue.
 */
class ImagenSitio extends Model
{
    protected $table = 'imagenes_sitio';

    protected $fillable = [
        'formato', 'carpeta', 'extension', 'anchos', 'ancho', 'alto',
        'nombre_original', 'peso_original', 'subida_por',
    ];

    protected $casts = [
        'anchos' => 'array',
        'ancho'  => 'integer',
        'alto'   => 'integer',
    ];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }

    public function ruta(int $ancho): string
    {
        return "sitio/{$this->carpeta}/{$ancho}.{$this->extension}";
    }

    public function url(?int $ancho = null): string
    {
        return Storage::disk('medios')->url($this->ruta($ancho ?? $this->ancho));
    }

    public function srcset(): string
    {
        return collect($this->anchos)
            ->map(fn (int $ancho) => $this->url($ancho) . " {$ancho}w")
            ->implode(', ');
    }

    /**
     * Lo que necesita un `<img>`: fuente, `srcset` y dimensiones explícitas
     * (sin ellas la página salta al cargar la foto).
     *
     * @return array{src: string, srcset: string, width: int, height: int, alt: string}
     */
    public function presentar(string $alt = ''): array
    {
        return [
            'src'    => $this->url(),
            'srcset' => $this->srcset(),
            'width'  => $this->ancho,
            'height' => $this->alto,
            'alt'    => $alt,
        ];
    }
}
