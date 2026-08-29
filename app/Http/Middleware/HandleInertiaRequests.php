<?php

namespace App\Http\Middleware;

use App\Providers\AuthServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user'    => $this->usuarioCompartido($request),
                'permisos' => $this->permisosCompartidos($request),
            ],
            'flash' => [
                'success'     => $request->session()->get('success'),
                'error'       => $request->session()->get('error'),
                'info'        => $request->session()->get('info'),
                'warning'     => $request->session()->get('warning'),
                'contacto_ok' => $request->session()->get('contacto_ok'),
            ],
            'isStaging' => app()->environment('staging'),

            // F — Que botones de acceso externo dibujar. La ruta de cada
            // proveedor comprueba el mismo interruptor por su cuenta: esconder
            // el boton no es control de acceso.
            'proveedores' => [
                'google'       => (bool) config('nodico.acceso.google'),
                'enlaceMagico' => (bool) config('nodico.acceso.enlace_magico'),
            ],

            // Datos de contacto y redes que consumen el footer y "Hablemos".
            'nodico' => [
                'email'           => config('nodico.contacto_email'),
                'telefono'        => config('nodico.telefono'),
                'telefonoE164'    => config('nodico.telefono_e164'),
                'direccion'       => config('nodico.direccion'),
                'direccionCorta'  => config('nodico.direccion_corta'),
                'mapsUrl'         => config('nodico.maps_url'),
                'mapsEmbed'       => config('nodico.maps_embed'),
                'horarios'        => config('nodico.horarios'),
                'horariosDetalle' => config('nodico.horarios_detalle'),
                'redes'           => config('nodico.redes'),
                'instagram'       => config('nodico.instagram_handle'),
            ],

            // SEO-01..04 — todo lo que consume <Meta>.
            'seo' => [
                'origen'   => $this->origenCanonico(),
                'canonica' => $this->origenCanonico() . $request->getPathInfo(),
                'negocio'  => $this->fichaNegocio(),
                'pagina'   => $this->metadatosDePagina($request),
            ],
        ];
    }

    /**
     * Que puede hacer esta persona, segun los Gates.
     *
     * Sirve para no ensenar en el menu secciones que van a responder 403.
     * **No es control de acceso**: cada ruta se autoriza en el servidor con su
     * propio `can:`; esconder un boton no protege nada.
     *
     * @return array<string, bool>
     */
    private function permisosCompartidos(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        $permisos = [];

        foreach (AuthServiceProvider::todos() as $permiso) {
            $permisos[$permiso] = Gate::allows($permiso);
        }

        return $permisos;
    }

    /**
     * Usuario que se comparte con el front, campo por campo.
     *
     * Antes se enviaba `$request->user()` entero, o sea el modelo completo:
     * entre otras cosas `notas_admin`, que son apuntes internos del equipo
     * sobre esa persona y que le llegaban a su propio navegador en cada
     * respuesta de Inertia.
     *
     * `rol` y `portalRuta` salen de aqui para que el navbar publico y los
     * layouts no tengan que adivinar el destino comparando cadenas: con un rol
     * desconocido `portalRuta` es `null` y la interfaz simplemente no ofrece
     * ningun portal, en vez de mandar a nadie a rebotar (A.1).
     *
     * @return array<string, mixed>|null
     */
    private function usuarioCompartido(Request $request): ?array
    {
        $usuario = $request->user();

        if (! $usuario) {
            return null;
        }

        return [
            'id'             => $usuario->id,
            'name'           => $usuario->name,
            'email'          => $usuario->email,
            'avatar'         => $usuario->avatar,
            'telefono'       => $usuario->telefono,
            'empresa'        => $usuario->empresa,
            'ocupacion'      => $usuario->ocupacion,
            'face_id_ok'     => $usuario->face_id_ok,
            'email_verified_at' => $usuario->email_verified_at,
            'verificado'     => $usuario->hasVerifiedEmail(),
            'rol'            => $usuario->rol?->value,
            'rolEtiqueta'    => $usuario->rol?->etiqueta(),
            'esOperativo'    => $usuario->esOperativo(),
            'portalRuta'     => $usuario->rutaInicio(),
            'estado'         => $usuario->estado->value,
            'estadoEtiqueta' => $usuario->estado->etiqueta(),
            'cuentaActiva'   => $usuario->cuentaActiva(),
        ];
    }

    /**
     * SEO-02 — origen canónico. **El sitio vive sin www** (decisión de Diego del
     * 29/08/2026), así que un `www.` al principio del host se quita siempre:
     * quien llegue por www sigue apuntando a la misma URL canónica.
     *
     * Ya no se cae al host de la petición. Con `trustProxies(at: '*')`,
     * `getSchemeAndHttpHost()` respeta la cabecera `X-Forwarded-Host`, de modo
     * que cualquiera podía elegir el host que aparecía en la canónica, en
     * `og:url` y en el JSON-LD. `app.url` ya apunta al host correcto en cada
     * entorno y no depende de la petición.
     */
    private function origenCanonico(): string
    {
        $origen = rtrim(config('nodico.host_canonico') ?: config('app.url'), '/');

        return preg_replace('#^(https?://)www\\.#i', '$1', $origen);
    }

    /**
     * SEO-01 — título, descripción e imagen social de la página actual.
     *
     * Se resuelve por nombre de ruta para que `app.blade.php` pueda emitirlos
     * en el HTML: los scrapers de enlaces no ejecutan JavaScript.
     */
    private function metadatosDePagina(Request $request): array
    {
        $paginas = config('nodico.seo_paginas', []);
        $ruta = $request->route()?->getName();

        $pagina = $paginas[$ruta] ?? $paginas['home'] ?? [];

        return [
            'titulo'      => $pagina['titulo'] ?? config('nodico.sufijo_titulo'),
            'descripcion' => $pagina['descripcion'] ?? '',
            'imagen'      => $this->origenCanonico() . '/img/og/' . ($pagina['imagen'] ?? 'home') . '.jpg',
        ];
    }

    /** SEO-03 — LocalBusiness con los datos que ya están en config/nodico.php. */
    private function fichaNegocio(): array
    {
        $origen = $this->origenCanonico();

        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'LocalBusiness',
            'name'        => 'Nódico',
            'description' => 'Coworking del Instituto Yucateco de Emprendedores en Mérida, Yucatán.',
            'url'         => $origen,
            'image'       => $origen . '/img/og/home.jpg',
            'logo'        => $origen . '/img/nodico/logo-nodico-blanco.png',
            'email'       => config('nodico.contacto_email'),
            'telephone'   => config('nodico.telefono_e164'),
            'address'     => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Avenida Principal, Industrias No Contaminantes 13613',
                'addressLocality' => 'Mérida',
                'addressRegion'   => 'Yucatán',
                'postalCode'      => '97110',
                'addressCountry'  => 'MX',
            ],
            'geo' => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 21.0527159,
                'longitude' => -89.6413298,
            ],
            'openingHoursSpecification' => [[
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'opens'     => '09:00',
                'closes'    => '19:00',
            ]],
            'sameAs' => array_values(array_filter((array) config('nodico.redes'))),
        ];
    }
}
