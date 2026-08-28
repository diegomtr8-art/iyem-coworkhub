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

    private function authProps(): array
    {
        return [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
        ];
    }
}
