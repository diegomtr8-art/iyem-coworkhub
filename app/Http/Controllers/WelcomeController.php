<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Plane;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        return Inertia::render('Welcome', [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
            'planes'      => Plane::where('activo', true)->orderBy('precio')->get(),
            'eventos'     => Evento::activos()->proximos()->limit(3)->get(),
        ]);
    }

    public function nosotros()
    {
        return Inertia::render('Nosotros', [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    }

    public function membresias()
    {
        return Inertia::render('Membresias', [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
            'planes'      => Plane::where('activo', true)->orderBy('precio')->get(),
        ]);
    }

    public function eventos()
    {
        return Inertia::render('Eventos', [
            'canLogin'    => Route::has('login'),
            'canRegister' => Route::has('register'),
            'proximos'    => Evento::activos()->proximos()->get(),
            'pasados'     => Evento::pasados()->limit(6)->get(),
        ]);
    }
}
