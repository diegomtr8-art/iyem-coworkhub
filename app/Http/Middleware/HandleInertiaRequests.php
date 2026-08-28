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
                'horarios'        => config('nodico.horarios'),
                'horariosDetalle' => config('nodico.horarios_detalle'),
                'redes'           => config('nodico.redes'),
                'instagram'       => config('nodico.instagram_handle'),
            ],
        ];
    }
}
