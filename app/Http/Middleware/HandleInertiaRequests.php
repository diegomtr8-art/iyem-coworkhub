<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
                'user' => $request->user(),
            ],
            'flash' => [
                'success'     => $request->session()->get('success'),
                'error'       => $request->session()->get('error'),
                'info'        => $request->session()->get('info'),
                'warning'     => $request->session()->get('warning'),
                'contacto_ok' => $request->session()->get('contacto_ok'),
            ],
            'isStaging' => app()->environment('staging'),

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
