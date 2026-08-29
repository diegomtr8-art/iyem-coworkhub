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
                'origen'   => $this->origenCanonico($request),
                'canonica' => $this->origenCanonico($request) . $request->getPathInfo(),
                'negocio'  => $this->fichaNegocio($request),
            ],
        ];
    }

    /**
     * SEO-02 — host canónico. Mientras `nodico.host_canonico` esté sin definir
     * se usa el host de la petición; en producción hay que fijarlo para decidir
     * de una vez si el sitio vive con o sin www.
     */
    private function origenCanonico(Request $request): string
    {
        $host = config('nodico.host_canonico');

        return $host
            ? rtrim($host, '/')
            : $request->getSchemeAndHttpHost();
    }

    /** SEO-03 — LocalBusiness con los datos que ya están en config/nodico.php. */
    private function fichaNegocio(Request $request): array
    {
        $origen = $this->origenCanonico($request);

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
