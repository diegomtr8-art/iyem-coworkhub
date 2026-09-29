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
     * @return array<string, array<string, array{reglas: array<int, mixed>, respaldo: Closure(): mixed}>>
     */
    public static function secciones(): array
    {
        return [
            'contacto' => [
                'email' => [
                    'reglas'   => ['required', 'string', 'email:rfc', 'max:120'],
                    'respaldo' => fn () => config('nodico.contacto_email'),
                ],
                'telefono' => [
                    'reglas'   => ['required', 'string', 'max:20', 'regex:/^[\d\s()+-]+$/', self::telefonoDeDiezDigitos()],
                    'respaldo' => fn () => config('nodico.telefono'),
                ],
                'direccion' => [
                    'reglas'   => ['required', 'string', 'max:160'],
                    'respaldo' => fn () => config('nodico.direccion'),
                ],
                'direccion_corta' => [
                    'reglas'   => ['required', 'string', 'max:80'],
                    'respaldo' => fn () => config('nodico.direccion_corta'),
                ],
                'maps_url' => [
                    'reglas'   => ['required', 'string', 'max:300', 'url:https', self::host('maps')],
                    'respaldo' => fn () => config('nodico.maps_url'),
                ],
                'horarios' => [
                    'reglas'   => ['required', 'string', 'max:60'],
                    'respaldo' => fn () => config('nodico.horarios'),
                ],
                'horarios_detalle' => [
                    'reglas'   => ['nullable', 'string', 'max:60'],
                    'respaldo' => fn () => config('nodico.horarios_detalle'),
                ],
            ],

            // Vacío es válido en las tres redes: quita el icono del pie.
            'redes' => [
                'instagram' => [
                    'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('instagram')],
                    'respaldo' => fn () => config('nodico.redes.instagram'),
                ],
                'facebook' => [
                    'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('facebook')],
                    'respaldo' => fn () => config('nodico.redes.facebook'),
                ],
                'linkedin' => [
                    'reglas'   => ['nullable', 'string', 'max:200', 'url:https', self::host('linkedin')],
                    'respaldo' => fn () => config('nodico.redes.linkedin'),
                ],
                // Va dentro de la URL del iframe del perfil: solo los caracteres
                // que Instagram admite en un nombre de usuario.
                'instagram_usuario' => [
                    'reglas'   => ['required', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
                    'respaldo' => fn () => config('nodico.instagram_handle'),
                ],
            ],
        ];
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
            $digitos = preg_replace('/\D/', '', (string) $valor);

            if (! preg_match('/^(52)?\d{10}$/', $digitos)) {
                $falla('El teléfono debe tener 10 dígitos.');
            }
        };
    }

    private static function host(string $red): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) use ($red): void {
            $host = strtolower((string) parse_url((string) $valor, PHP_URL_HOST));

            if (! in_array($host, self::HOSTS[$red], true)) {
                $falla('El enlace tiene que ser de ' . self::HOSTS[$red][0] . '.');
            }
        };
    }
}
