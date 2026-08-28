<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Evento;
use App\Models\Plane;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Welcome', [
            ...$this->authProps(),
            'planes'  => Plane::publicos()->get(),
            'salon'   => Espacio::salonesPublicados()->first(),
            'eventos' => Evento::activos()->proximos()->limit(3)->get(),
        ]);
    }

    public function nosotros()
    {
        return Inertia::render('Nosotros', $this->authProps());
    }

    public function membresias()
    {
        return Inertia::render('Membresias', [
            ...$this->authProps(),
            'planes' => Plane::publicos()->get(),
        ]);
    }

    /** `/eventos` — salones para eventos (equivalente a /salones en Odoo). */
    public function salones()
    {
        return Inertia::render('Salones', [
            ...$this->authProps(),
            'salones' => Espacio::salonesPublicados()->get(),
        ]);
    }

    /** `/actividades` — comunidad y talleres (equivalente a /comunidad en Odoo). */
    public function comunidad()
    {
        return Inertia::render('Comunidad', [
            ...$this->authProps(),
            'proximos'  => Evento::activos()->proximos()->limit(6)->get(),
            'pasados'   => Evento::activos()->pasados()->limit(6)->get(),
            'salon'     => Espacio::salonesPublicados()->first(),
            'lumaEmbed' => config('nodico.luma_embed'),
        ]);
    }

    public function privacidad()
    {
        return Inertia::render('Legal/Documento', [
            ...$this->authProps(),
            'titulo'      => 'Aviso de privacidad',
            'descripcion' => 'Aviso de privacidad de Nódico, el coworking del Instituto Yucateco de Emprendedores.',
            'provisional' => true,
            'secciones'   => [
                [
                    'titulo'   => 'Responsable de los datos',
                    'parrafos' => [
                        'El Instituto Yucateco de Emprendedores (IYEM), titular de la marca Nódico, es el responsable del tratamiento de los datos personales que usted proporcione a través de este sitio.',
                    ],
                ],
                [
                    'titulo'   => 'Datos que recabamos',
                    'parrafos' => [
                        'A través del formulario de contacto recabamos nombre, teléfono, correo electrónico, empresa, asunto y el mensaje que usted escriba.',
                        'Estos datos se utilizan únicamente para responder su solicitud y darle seguimiento comercial sobre las membresías y servicios de Nódico.',
                    ],
                ],
                [
                    'titulo'   => 'Ejercicio de derechos ARCO',
                    'parrafos' => [
                        'Puede solicitar el acceso, rectificación, cancelación u oposición al tratamiento de sus datos escribiendo a contacto@nodico.com.mx.',
                    ],
                ],
            ],
        ]);
    }

    public function terminos()
    {
        return Inertia::render('Legal/Documento', [
            ...$this->authProps(),
            'titulo'      => 'Términos y condiciones',
            'descripcion' => 'Términos y condiciones de uso del sitio y de las membresías de Nódico.',
            'provisional' => true,
            'secciones'   => [
                [
                    'titulo'   => 'Objeto',
                    'parrafos' => [
                        'Estos términos regulan el uso del sitio de Nódico y la contratación de sus membresías y servicios de renta de salones.',
                    ],
                ],
                [
                    'titulo'   => 'Membresías y pagos',
                    'parrafos' => [
                        'Los precios publicados están expresados en pesos mexicanos. El cobro se procesa a través de Stripe; Nódico no almacena datos de tarjetas.',
                        'La vigencia de cada membresia corresponde al periodo indicado en su descripción.',
                    ],
                ],
                [
                    'titulo'   => 'Uso del espacio',
                    'parrafos' => [
                        'El acceso al espacio requiere el registro previo del miembro. Nódico se reserva el derecho de admisión conforme a su reglamento interno.',
                    ],
                ],
            ],
        ]);
    }

    private function authProps(): array
    {
        return [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
        ];
    }
}
