<?php

namespace App\Servicios\Sitio;

use InvalidArgumentException;

/**
 * Cada hueco de imagen del sitio tiene una proporción, y no son iguales: el
 * hero es vertical, la portada de Comunidad es panorámica y las tarjetas de
 * espacios son 4:3. La imagen se recorta al subirla a la proporción de su hueco
 * y se guarda en varios anchos para el `srcset`: el administrador sube **un**
 * archivo, nunca tres.
 *
 * `minimo` es el ancho útil **después** de recortar. Una foto de 4000×600
 * cumple cualquier ancho, pero recortada a 9:16 se queda en 337 px.
 */
final class FormatosDeImagen
{
    /**
     * @var array<string, array{nombre: string, proporcion: array{int, int}, minimo: int, anchos: array<int, int>, extension: string, calidad: int}>
     */
    private const FORMATOS = [
        'retrato' => [
            'nombre'     => 'Vertical 9:16',
            'proporcion' => [9, 16],
            'minimo'     => 1080,
            'anchos'     => [640, 1080],
            'extension'  => 'webp',
            'calidad'    => 80,
        ],
        'panoramica' => [
            'nombre'     => 'Panorámica 2:1',
            'proporcion' => [2, 1],
            'minimo'     => 1600,
            'anchos'     => [640, 1280, 1920],
            'extension'  => 'webp',
            'calidad'    => 80,
        ],
        'ancha' => [
            'nombre'     => 'Horizontal 16:9',
            'proporcion' => [16, 9],
            'minimo'     => 1280,
            'anchos'     => [640, 1280, 1920],
            'extension'  => 'webp',
            'calidad'    => 80,
        ],
        'horizontal' => [
            'nombre'     => 'Horizontal 3:2',
            'proporcion' => [3, 2],
            'minimo'     => 1280,
            'anchos'     => [640, 1280, 1920],
            'extension'  => 'webp',
            'calidad'    => 80,
        ],
        'tarjeta' => [
            'nombre'     => 'Tarjeta 4:3',
            'proporcion' => [4, 3],
            'minimo'     => 1200,
            'anchos'     => [800, 1600],
            'extension'  => 'webp',
            'calidad'    => 80,
        ],
        // Imagen para WhatsApp, LinkedIn y compañía. Va en JPG porque varios
        // de esos lectores no abren WebP, y a tamaño fijo: 1200×630.
        'social' => [
            'nombre'     => 'Tarjeta social 1200×630',
            'proporcion' => [40, 21],
            'minimo'     => 1200,
            'anchos'     => [1200],
            'extension'  => 'jpg',
            'calidad'    => 85,
        ],
    ];

    /** @return array{nombre: string, proporcion: array{int, int}, minimo: int, anchos: array<int, int>, extension: string, calidad: int} */
    public static function de(string $formato): array
    {
        return self::FORMATOS[$formato]
            ?? throw new InvalidArgumentException("El formato de imagen «{$formato}» no existe.");
    }

    public static function existe(string $formato): bool
    {
        return array_key_exists($formato, self::FORMATOS);
    }

    /** @return array<string, array{nombre: string, proporcion: array{int, int}, minimo: int, anchos: array<int, int>, extension: string, calidad: int}> */
    public static function todos(): array
    {
        return self::FORMATOS;
    }
}
