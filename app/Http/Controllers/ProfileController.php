<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Consentimiento;
use App\Support\DocumentosLegales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            // E — Derechos ARCO: que acepto, cuando y desde donde.
            'consentimientos' => $request->user()->consentimientos()
                ->orderByDesc('aceptado_en')
                ->get()
                ->map(fn (Consentimiento $c) => [
                    'etiqueta'   => $c->etiqueta,
                    'version'    => $c->version,
                    'aceptadoEn' => $c->aceptado_en->format('d/m/Y H:i'),
                    'ip'         => $c->ip,
                ]),

            // BE-04 — sigue sin validacion juridica del IYEM, y se dice.
            'legalProvisional' => app(DocumentosLegales::class)->algunoEsProvisional(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Solo el nombre. El correo tiene su propio flujo seguro en «Mi
        // seguridad» (Fase 4.C), así que aquí no se toca `email` ni su verificación.
        $request->user()->fill($request->validated());
        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
