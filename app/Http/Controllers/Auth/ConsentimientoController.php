<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Models\Consentimiento;
use App\Support\DocumentosLegales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E — Pantalla de consentimiento.
 *
 * Aparece en dos momentos:
 *
 * 1. **Primer acceso con Google.** Quien entra por ahi nunca ve el formulario
 *    de registro, asi que nunca marca la casilla legal. Sin esta pantalla, esas
 *    cuentas quedarian dentro del portal sin haber aceptado nada, que es
 *    justo lo que la LFPDPPP no permite.
 * 2. **Cuando el IYEM publica una version nueva.** Como se guarda la version
 *    aceptada y no un booleano, a quien tenga la anterior se le vuelve a pedir.
 */
class ConsentimientoController extends Controller
{
    use RedirigeAlPortal;

    public function __construct(private readonly DocumentosLegales $documentos)
    {
    }

    public function mostrar(Request $request): Response|RedirectResponse
    {
        $usuario   = $request->user();
        $pendiente = $usuario->consentimientosPendientes();

        // Nada que aceptar: no tiene sentido dejar la pantalla accesible.
        if ($pendiente === []) {
            return $this->alPortal($usuario);
        }

        return Inertia::render('Auth/Consentimiento', [
            'nombre'  => explode(' ', trim($usuario->name))[0] ?? null,
            'version' => $this->documentos->etiquetaDeVersion(),
            // Ya habia aceptado algo antes: el texto cambia de «antes de
            // entrar» a «actualizamos nuestros terminos».
            'esActualizacion' => $usuario->consentimientos()->exists(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $request->validate(
            ['acepta' => 'accepted'],
            ['acepta.accepted' => 'Necesitamos que aceptes para poder continuar.'],
        );

        $usuario = $request->user();

        Consentimiento::registrar($usuario, $request);

        return $this->alPortal($usuario)
            ->with('success', 'Gracias. Queda registrado.');
    }
}
