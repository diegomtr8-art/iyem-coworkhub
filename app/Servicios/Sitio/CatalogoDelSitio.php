<?php

namespace App\Servicios\Sitio;

use App\Servicios\Sitio\Paginas\Comun;
use App\Servicios\Sitio\Paginas\General;
use App\Servicios\Sitio\Paginas\Inicio;
use Closure;
use InvalidArgumentException;

/**
 * Qué contenido del sitio público se puede editar desde el panel, con qué
 * reglas y cuál es su respaldo. Las secciones de cada página viven en
 * `Paginas/`; aquí solo se juntan en el orden del sitio.
 *
 * Es el **único** sitio donde vive el valor por omisión de cada campo: cuando
 * una sección se muda aquí, su literal desaparece del componente Vue. Que el
 * mismo texto viva en dos lugares es garantía de que algún día discrepen.
 *
 * El respaldo es lo que hace reversible todo el módulo: con la tabla `ajustes`
 * vacía —base recién sembrada, despliegue nuevo, fila borrada por error— el
 * sitio se ve exactamente como antes de existir el panel.
 *
 * Las reglas son las mismas al guardar (el panel rechaza el cambio) y al leer
 * (un valor que ya no las cumple se ignora y se sirve el respaldo). Una sola
 * definición para las dos puertas. Inventario y decisiones: docs/CMS-PAGINA-WEB.md.
 */
final class CatalogoDelSitio
{
    /**
     * Hosts que aceptan los enlaces a redes y mapas. Un campo de URL libre en el
     * pie de página es una forma de mandar a todo el que lo pulse a cualquier
     * sitio; aquí cada enlace solo puede apuntar a donde dice que apunta.
     */
    public const HOSTS = [
        'maps'      => ['maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'www.google.com', 'google.com'],
        'instagram' => ['www.instagram.com', 'instagram.com'],
        'facebook'  => ['www.facebook.com', 'facebook.com', 'm.facebook.com'],
        'linkedin'  => ['www.linkedin.com', 'linkedin.com', 'mx.linkedin.com'],
    ];

    /** @var array<string, array>|null */
    private static ?array $secciones = null;

    /**
     * Las pantallas del panel, en el orden del menú del sitio. El administrador
     * piensa en «el inicio, la sección de servicios», no en claves.
     *
     * @return array<string, array{titulo: string, descripcion: string, ruta: string}>
     */
    public static function paginas(): array
    {
        return [
            'inicio' => [
                'titulo'      => 'Inicio',
                'descripcion' => 'La portada del sitio.',
                'ruta'        => 'home',
            ],
            'comun' => [
                'titulo'      => 'Todas las páginas',
                'descripcion' => 'Lo que se repite al final de cada página: aliados, «Hablemos» y pie.',
                'ruta'        => 'home',
            ],
            'general' => [
                'titulo'      => 'Datos generales',
                'descripcion' => 'Contacto, redes sociales y la ficha del negocio para buscadores.',
                'ruta'        => 'home',
            ],
        ];
    }

    /**
     * Cada sección editable: a qué pantalla del panel pertenece, dónde sale en
     * el sitio y sus campos (ver `Campo`). El orden es el del sitio.
     *
     * @return array<string, array{pagina: string, titulo: string, aparece: string, ancla: ?string, campos: array<string, array>}>
     */
    public static function secciones(): array
    {
        return self::$secciones ??= [
            ...Inicio::secciones(),
            ...Comun::secciones(),
            ...General::secciones(),
        ];
    }

    /** @return array<string, array> */
    public static function campos(string $clave): array
    {
        return self::secciones()[$clave]['campos']
            ?? throw new InvalidArgumentException("La sección «{$clave}» no está en el catálogo del sitio.");
    }

    /** @return array<int, string> */
    public static function claves(): array
    {
        return array_keys(self::secciones());
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::secciones());
    }

    /** Tope de caracteres de un campo, sacado de su regla `max:`. Lo usa el contador del panel. */
    public static function maximo(array $campo): ?int
    {
        if ($campo['tipo'] === 'lista') {
            return null;
        }

        foreach ($campo['reglas'] as $regla) {
            if (is_string($regla) && str_starts_with($regla, 'max:')) {
                return (int) substr($regla, 4);
            }
        }

        return null;
    }

    /**
     * Número mexicano de diez dígitos, con o sin lada internacional. El E.164
     * que usan `tel:` y el JSON-LD se deriva de aquí: pedirlo aparte es darle
     * a alguien la oportunidad de que los dos no coincidan.
     */
    public static function telefonoE164(string $telefono): string
    {
        $digitos = preg_replace('/\D/', '', $telefono);

        return '+52' . substr($digitos, -10);
    }

    public static function telefonoDeDiezDigitos(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            // Si no es texto ya lo rechaza la regla `string`; Laravel sigue
            // corriendo las demás reglas, y esta no debe reventar con un arreglo.
            if (! is_string($valor)) {
                return;
            }

            $digitos = preg_replace('/\D/', '', $valor);

            if (! preg_match('/^(52)?\d{10}$/', $digitos)) {
                $falla('El teléfono debe tener 10 dígitos.');
            }
        };
    }
}
