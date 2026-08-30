<?php

namespace App\Http\Controllers;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use App\Models\DispositivoConfiable;
use App\Models\IdentidadSocial;
use App\Support\DosFactores;
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
    public function __construct(
        private readonly SesionesActivas $sesiones,
        private readonly DosFactores $dosFactores,
    ) {
    }

    public function index(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Seguridad/Index', [
            // C — Correo de la cuenta y, si la hay, la solicitud de cambio viva.
            'correo' => [
                'actual'     => $usuario->email,
                'verificado' => $usuario->hasVerifiedEmail(),
                'pendiente'  => $usuario->correoPendiente(),
            ],

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

            // D — Estado del segundo factor y equipos en los que no se vuelve
            // a pedir.
            'dosFactores' => [
                'activo'      => $usuario->tieneDosFactores(),
                'obligatorio' => $this->dosFactores->esObligatorioPara($usuario),
                'desde'       => $usuario->dos_factores_confirmado_en?->format('d/m/Y'),
                'codigosSinUsar' => $usuario->codigosRecuperacion()->whereNull('usado_en')->count(),
            ],

            'dispositivosConfiables' => $usuario->dispositivosConfiables()
                ->where('expira_en', '>', now())
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (DispositivoConfiable $d) => [
                    'id'       => $d->id,
                    'ip'       => $d->ip,
                    'caduca'   => $d->expira_en->format('d/m/Y'),
                    'desde'    => $d->created_at?->format('d/m/Y'),
                ]),
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
     * D — Quitar la confianza de un equipo.
     *
     * Existe justo para el caso de haber marcado «confiar» en un equipo
     * prestado: hay que poder revocarlo **sin** acceso a ese equipo, y por eso
     * la confianza vive como fila en la base y no solo como cookie.
     */
    public function olvidarDispositivo(Request $request, DispositivoConfiable $dispositivo): RedirectResponse
    {
        abort_unless($dispositivo->user_id === $request->user()->id, 403);

        $dispositivo->delete();

        return back()->with('success', 'Ese equipo volvera a pedir el codigo.');
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
        // `$sesion` es una referencia opaca, no el identificador real: ver
        // `SesionesActivas::referencia()`.
        if (! $this->sesiones->cerrar($request->user(), $sesion)) {
            return back()->with('error', 'Esa sesión ya no existe o no es tuya.');
        }

        EventoAutenticacion::registrar(
            EventoAuth::CierreRemoto,
            $request->user(),
            contexto: ['sesiones_cerradas' => 1],
        );

        return back()->with('success', 'Sesión cerrada.');
    }
}
