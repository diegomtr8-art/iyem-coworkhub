<?php

namespace App\Http\Controllers;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use App\Models\IdentidadSocial;
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

            // C — Cuentas externas vinculadas, y si se puede soltar alguna.
            'identidades'       => $usuario->identidades()->get()->map(fn (IdentidadSocial $i) => [
                'id'          => $i->id,
                'etiqueta'    => $i->etiqueta,
                'correo'      => $i->correo,
                'vinculadaEn' => $i->created_at?->format('d/m/Y'),
            ]),
            'tieneContrasena'   => $usuario->tieneContrasena(),
            'metodosDeAcceso'   => $usuario->metodosDeAcceso(),
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

    /**
     * C — Desvincular una cuenta externa.
     *
     * **Nunca se suelta el ultimo metodo de acceso.** Una persona que entro con
     * Google y no puso contrasena, al desvincular Google se quedaria fuera de
     * su propia cuenta sin ninguna via de volver: ni contrasena que recuperar,
     * ni identidad con la que entrar. Se comprueba en el servidor, no
     * escondiendo el boton.
     */
    public function desvincular(Request $request, IdentidadSocial $identidad): RedirectResponse
    {
        $usuario = $request->user();

        // Sin este `abort` se podria soltar la identidad de otra persona
        // pasando su identificador.
        abort_unless($identidad->user_id === $usuario->id, 403);

        if ($usuario->metodosDeAcceso() <= 1) {
            return back()->with('error', 'Es tu unica forma de entrar a Nodico. Pon una contrasena antes de desvincularla.');
        }

        $proveedor = $identidad->proveedor;
        $identidad->delete();

        EventoAutenticacion::registrar(
            EventoAuth::DesvinculoSocial,
            $usuario,
            contexto: ['proveedor' => $proveedor],
        );

        return back()->with('success', 'Cuenta de ' . ucfirst($proveedor) . ' desvinculada.');
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
