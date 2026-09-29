<?php

namespace App\Servicios\Sitio;

use Closure;

/**
 * Qué contenido del sitio público se puede editar desde el panel, con qué
 * reglas y cuál es su respaldo.
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
    private const HOSTS = [
        'maps'      => ['maps.app.goo.gl', 'goo.gl', 'maps.google.com', 'www.google.com', 'google.com'],
        'instagram' => ['www.instagram.com', 'instagram.com'],
        'facebook'  => ['www.facebook.com', 'facebook.com', 'm.facebook.com'],
        'linkedin'  => ['www.linkedin.com', 'linkedin.com', 'mx.linkedin.com'],
    ];

    /**
     * Las pantallas del panel, en el orden del menú del sitio. El administrador
     * piensa en «el inicio, la sección de servicios», no en claves.
     *
     * @return array<string, array{titulo: string, descripcion: string, ruta: string}>
     */
    public static function paginas(): array
    {
        return [
            'general' => [
                'titulo'      => 'Datos generales',
                'descripcion' => 'Contacto y redes sociales. Salen en el pie de todas las páginas, en el bloque «Hablemos» y en la portada.',
                'ruta'        => 'home',
            ],
        ];
    }

    /**
     * Cada sección editable: a qué pantalla del panel pertenece, dónde sale en
     * el sitio y sus campos. El orden es el del sitio.
     *
     * Por campo: `etiqueta` y `ayuda` son lo que lee la coordinación; `tipo`
     * elige el control (texto, parrafo, email, telefono, url, usuario);
     * `reglas` valida al guardar y al leer; `respaldo` es el valor si no hay
     * nada guardado.
     *
     * @return array<string, array{pagina: string, titulo: string, aparece: string, ancla: ?string, campos: array<string, array{etiqueta: string, tipo: string, ayuda?: string, reglas: array<int, mixed>, respaldo: Closure(): mixed}>}>
     */
    public static function secciones(): array
    {
        return [
            'contacto' => [
                'pagina'  => 'general',
                'titulo'  => 'Datos de contacto',
                'aparece' => 'Pie de todas las páginas, bloque «Hablemos» y franja inferior de la portada.',
                'ancla'   => '#hablemos',
                'campos'  => [
                    'email' => [
                        'etiqueta' => 'Correo de contacto',
                        'tipo'     => 'email',
                        'ayuda'    => 'También es a donde llegan los mensajes del formulario «Hablemos».',
                        'reglas'   => ['required', 'string', 'email:rfc', 'max:120'],
                        'respaldo' => fn () => config('nodico.contacto_email'),
                    ],
                    'telefono' => [
                        'etiqueta' => 'Teléfono',
                        'tipo'     => 'telefono',
                        'ayuda'    => 'Diez dígitos, escrito como quieras que se lea. Al pulsarlo en el celular, marca.',
                        'reglas'   => ['required', 'string', 'max:20', 'regex:/^[\d\s()+-]+$/', self::telefonoDeDiezDigitos()],
                        'respaldo' => fn () => config('nodico.telefono'),
                    ],
                    'direccion' => [
                        'etiqueta' => 'Dirección completa',
                        'tipo'     => 'parrafo',
                        'ayuda'    => 'Sale en el pie de página.',
                        'reglas'   => ['required', 'string', 'max:160'],
                        'respaldo' => fn () => config('nodico.direccion'),
                    ],
                    'direccion_corta' => [
                        'etiqueta' => 'Dirección corta',
                        'tipo'     => 'texto',
                        'ayuda'    => 'Sale en la portada y en «Hablemos», donde no cabe la completa.',
                        'reglas'   => ['required', 'string', 'max:80'],
                        'respaldo' => fn () => config('nodico.direccion_corta'),
                    ],
                    'maps_url' => [
                        'etiqueta' => 'Enlace «Cómo llegar»',
                        'tipo'     => 'url',
                        'ayuda'    => 'El enlace de Google Maps que da el botón «Compartir» de la ficha del lugar.',
                        'reglas'   => ['required', 'string', 'max:300', 'url:https', self::host('maps')],
                        'respaldo' => fn () => config('nodico.maps_url'),
                    ],
                    'horarios' => [
                        'etiqueta' => 'Horario',
                        'tipo'     => 'texto',
                        'ayuda'    => 'Solo es el texto que se lee. El horario en el que se puede reservar se cambia en la configuración del sistema.',
                        'reglas'   => ['required', 'string', 'max:60'],
                        'respaldo' => fn () => config('nodico.horarios'),
                    ],
                    'horarios_detalle' => [
                        'etiqueta' => 'Nota del horario',
                        'tipo'     => 'texto',
                        'ayuda'    => 'Línea pequeña debajo del horario. Déjala vacía para quitarla.',
                        'reglas'   => ['nullable', 'string', 'max:60'],
                        'respaldo' => fn () => config('nodico.horarios_detalle'),
                    ],
                ],
            ],

            // Vacío es válido en las tres redes: quita el icono del pie.
            'redes' => [
                'pagina'  => 'general',
                'titulo'  => 'Redes sociales',
                'aparece' => 'Iconos del pie de todas las páginas y sección de Instagram de la portada y de Comunidad.',
                'ancla'   => null,
                'campos'  => [
                    'instagram' => [
                        'etiqueta' => 'Instagram',
                        'tipo'     => 'url',
                        'ayuda'    => 'Enlace al perfil. Vacío quita el icono.',
                        'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('instagram')],
                        'respaldo' => fn () => config('nodico.redes.instagram'),
                    ],
                    'facebook' => [
                        'etiqueta' => 'Facebook',
                        'tipo'     => 'url',
                        'ayuda'    => 'Enlace a la página. Vacío quita el icono.',
                        'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('facebook')],
                        'respaldo' => fn () => config('nodico.redes.facebook'),
                    ],
                    'linkedin' => [
                        'etiqueta' => 'LinkedIn',
                        'tipo'     => 'url',
                        'ayuda'    => 'Enlace al perfil. Vacío quita el icono.',
                        'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('linkedin')],
                        'respaldo' => fn () => config('nodico.redes.linkedin'),
                    ],
                    // Va dentro de la URL del iframe del perfil: solo los caracteres
                    // que Instagram admite en un nombre de usuario.
                    'instagram_usuario' => [
                        'etiqueta' => 'Usuario de Instagram',
                        'tipo'     => 'usuario',
                        'ayuda'    => 'Sin la @. Es la cuenta cuyas publicaciones se muestran en la portada y en Comunidad.',
                        'reglas'   => ['required', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
                        'respaldo' => fn () => config('nodico.instagram_handle'),
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array{etiqueta: string, tipo: string, ayuda?: string, reglas: array<int, mixed>, respaldo: Closure(): mixed}> */
    public static function campos(string $clave): array
    {
        return self::secciones()[$clave]['campos']
            ?? throw new \InvalidArgumentException("La sección «{$clave}» no está en el catálogo del sitio.");
    }

    /** Tope de caracteres de un campo, sacado de su regla `max:`. Lo usa el contador del panel. */
    public static function maximo(array $campo): ?int
    {
        foreach ($campo['reglas'] as $regla) {
            if (is_string($regla) && str_starts_with($regla, 'max:')) {
                return (int) substr($regla, 4);
            }
        }

        return null;
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

    private static function telefonoDeDiezDigitos(): Closure
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

    private static function host(string $red): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) use ($red): void {
            if (! is_string($valor)) {
                return;
            }

            $host = strtolower((string) parse_url($valor, PHP_URL_HOST));

            if (! in_array($host, self::HOSTS[$red], true)) {
                $falla('El enlace tiene que ser de ' . self::HOSTS[$red][0] . '.');
            }
        };
    }
}
