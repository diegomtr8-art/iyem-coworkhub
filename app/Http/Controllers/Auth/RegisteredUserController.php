<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Comunicado;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'telefono' => 'nullable|string|max:20',
            'empresa'  => 'nullable|string|max:255',
            'ocupacion'=> 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'tipo'      => 'miembro',
            'telefono'  => $request->telefono,
            'empresa'   => $request->empresa,
            'ocupacion' => $request->ocupacion,
            'face_id_ok'=> false,
        ]);

        Comunicado::create([
            'user_id' => $user->id,
            'titulo'  => '¡Bienvenido a Nodico, ' . explode(' ', $user->name)[0] . '!',
            'mensaje' => 'Tu cuenta ha sido creada exitosamente. El siguiente paso es visitar nuestras instalaciones para registrar tu Face ID y activar tu acceso. Nuestro equipo te contactará pronto.',
            'tipo'    => 'bienvenida',
            'leido'   => false,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('portal.dashboard'));
    }
}
