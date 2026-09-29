<?php

namespace App\Servicios\Sitio\Paginas;

/**
 * Las fotos fijas del sitio: el respaldo de cada hueco de imagen mientras no
 * se suba otra desde el panel. `src`, `srcset`, medidas y `alt` son
 * exactamente los que pintaban los componentes antes del módulo, para que con
 * `ajustes` vacía el sitio quede idéntico.
 *
 * Viven en public/img/nodico, en el repositorio. Las que suba la coordinación
 * van al disco `medios` y nunca aquí (docs/DEPLOY.md).
 */
final class Fotos
{
    /** Juego de tres tamaños (`-640`, `-1280` y el original). */
    private static function juego(string $nombre, int $ancho, int $alto, string $alt, int $anchoOriginal = 1920): array
    {
        $base = "/img/nodico/{$nombre}";

        return [
            'src'    => "{$base}.webp",
            'srcset' => "{$base}-640.webp 640w, {$base}-1280.webp 1280w, {$base}.webp {$anchoOriginal}w",
            'width'  => $ancho,
            'height' => $alto,
            'alt'    => $alt,
        ];
    }

    /** Un solo archivo, sin `srcset`. */
    private static function suelta(string $archivo, int $ancho, int $alto, string $alt = ''): array
    {
        return ['src' => "/img/nodico/{$archivo}", 'srcset' => null, 'width' => $ancho, 'height' => $alto, 'alt' => $alt];
    }

    public static function heroInicio(): array
    {
        return self::juego('hero-inicio', 1079, 1920, 'Área de coworking de Nódico en Mérida', 1079);
    }

    /** Las dos tarjetas grandes de Servicios: decorativas, el texto va encima. */
    public static function serviciosGrandes(): array
    {
        return [
            self::suelta('mision.webp', 1920, 1079),
            // Antes era vision.webp, el área de trabajo abierta: la tarjeta
            // prometía un estudio y enseñaba un coworking. Es la cabina.
            self::suelta('sala-contenido.webp', 1920, 1079),
        ];
    }

    public static function espacios(): array
    {
        $tarjeta = fn (string $nombre, string $alt) => [
            'src'    => "/img/nodico/{$nombre}.webp",
            'srcset' => "/img/nodico/{$nombre}-800.webp 800w, /img/nodico/{$nombre}.webp 1600w",
            'width'  => 1600,
            'height' => 1200,
            'alt'    => $alt,
        ];

        return [
            $tarjeta('espacio-cubiculos', 'Cubículo privado de Nódico con escritorio y sillas junto a una ventana'),
            $tarjeta('espacio-salas-juntas', 'Personas reunidas alrededor de la mesa de juntas de Nódico'),
            $tarjeta('espacio-contenido', 'Cabina de podcast de Nódico con micrófonos, paneles acústicos y aro de luz'),
            $tarjeta('espacio-fotografia', 'Estudio de fotografía de Nódico con softboxes, aro de luz y fondo removible'),
        ];
    }

    /**
     * Fondos de los paneles de Beneficios: decorativos. Son fotos del espacio,
     * no de cada beneficio (varios son conceptos abstractos).
     */
    public static function beneficios(): array
    {
        return [
            self::suelta('salon-detalle.webp', 1200, 800),
            self::suelta('mision.webp', 1200, 800),
            self::suelta('comunidad-fondo.webp', 1200, 800),
            self::suelta('salon-yucatan-emprende-1.webp', 1200, 800),
            self::suelta('nosotros-hero.webp', 1200, 800),
        ];
    }

    public static function daypass(): array
    {
        return self::juego('daypass-emprendedor', 1677, 1920, 'Emprendedores del interior del estado trabajando en Nódico', 1677);
    }

    public static function salonInicio(): array
    {
        return self::juego('salon-yucatan-emprende-2', 1920, 1440, 'Salón de eventos de Nódico montado para una conferencia');
    }

    public static function nosotrosPortada(): array
    {
        return self::juego('nosotros-hero', 1920, 1280, 'Miembros de la comunidad Nódico');
    }

    public static function mision(): array
    {
        return self::juego('mision', 1920, 1079, 'Emprendedores colaborando en una mesa de trabajo de Nódico');
    }

    public static function vision(): array
    {
        return self::juego('vision', 1920, 1079, '');
    }

    public static function comunidadPortada(): array
    {
        return self::juego('comunidad-fondo', 1920, 960, '');
    }

    public static function salonesPortada(): array
    {
        return [
            'src'    => '/img/nodico/salon-yucatan-emprende-1.webp',
            'srcset' => '/img/nodico/salon-yucatan-emprende-1-640.webp 640w, /img/nodico/salon-yucatan-emprende-1.webp 1079w',
            'width'  => 1079,
            'height' => 1920,
            'alt'    => 'Salón Yucatán Emprende montado para un evento',
        ];
    }

    public static function coffee(): array
    {
        return self::juego('salon-detalle', 1920, 1079, '');
    }

    /** Fondo del panel de marca de las pantallas de acceso. */
    public static function acceso(): array
    {
        return self::suelta('comunidad-fondo.webp', 1920, 960);
    }

    /** Tarjeta social (og:image) de una página: JPG de 1200×630 en /img/og. */
    public static function social(string $nombre): array
    {
        return ['src' => "/img/og/{$nombre}.jpg", 'srcset' => null, 'width' => 1200, 'height' => 630, 'alt' => ''];
    }
}
