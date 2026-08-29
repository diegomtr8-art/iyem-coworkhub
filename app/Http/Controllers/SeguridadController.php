<?php

namespace App\Http\Controllers;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use App\Support\SesionesActivas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * B — «Mi seguridad»: sesiones abiertas y bitácora propia.
 *
 * Cada quien ve **lo suyo y solo lo suyo**. La vista de administración, con la
 * bitácora completa, es otra ruta con su propio permiso: mezclar ambas en un
 * controlador con un `if` sería exactamente lo que A.3 vino a quitar.
 */
class SeguridadController extends Controller
{
    public function __construct(private readonly SesionesActivas $sesiones)
    {
    }

    public function index(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Seguridad/Index', [
            'sesiones'          => $this->sesiones->listar($usuario, $request->session()->getId()),
            'sesionesLegibles'  => $this->sesiones->disponible(),
            'eventos'           => EventoAutenticacion::de($usuario)
                ->recientes()
                ->limit(50)
                ->get()
                ->map(fn (EventoAutenticacion $evento) => [
                    'id'        => $evento->id,
                    'etiqueta'  => $evento->etiqueta,
                    'exito'     => $evento->exito,
                    'delicado'  => $evento->evento?->esDelicado() ?? false,
                    'ip'        => $evento->ip,
                    'agente'    => $evento->agente,
                    'cuando'    => $evento->created_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    /**
     * Cierra el resto de sesiones. Va detrás de `password.confirm`: si alguien
     * se deja la sesión abierta en un equipo prestado, que no pueda expulsar al
     * dueño de sus propios dispositivos sin saber su contraseña.
     */
    public function cerrarOtras(Request $request): RedirectResponse
    {
        $cerradas = $this->sesiones->cerrarOtras($request->user(), $request->session()->getId());

        EventoAutenticacion::registrar(
            EventoAuth::CierreRemoto,
            $request->user(),
            contexto: ['sesiones_cerradas' => $cerradas],
        );

        return back()->with('success', $cerradas === 0
            ? 'No había otras sesiones abiertas.'
            : "Se cerraron {$cerradas} sesión" . ($cerradas === 1 ? '' : 'es') . ' en otros dispositivos.');
    }

    public function cerrarUna(Request $request, string $sesion): RedirectResponse
    {
        if ($sesion === $request->session()->getId()) {
            return back()->with('error', 'Esa es la sesión que estás usando ahora. Usa «Cerrar sesión» para salir.');
        }

        $this->sesiones->cerrar($request->user(), $sesion);

        EventoAutenticacion::registrar(
            EventoAuth::CierreRemoto,
            $request->user(),
            contexto: ['sesiones_cerradas' => 1],
        );

        return back()->with('success', 'Sesión cerrada.');
    }
}
