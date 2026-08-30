<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\EventoAuth;
use App\Models\EntradaBitacora;
use App\Models\EventoAutenticacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora, en dos registros (Fases B y 3.11).
 *
 * **Acceso** responde a «¿alguien entró a esta cuenta?» y «¿nos están probando
 * contraseñas?». **Operación** responde a otra cosa: «¿quién le tocó las horas
 * a este miembro y por qué?».
 *
 * Están separadas porque se consultan por motivos distintos y porque
 * mezclarlas las haría inútiles a las dos: el ruido de los intentos de ingreso
 * enterraría los seis ajustes de horas del mes.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): Response
    {
        $registro = $request->string('registro')->toString() ?: 'operacion';

        return Inertia::render('Bitacora/Index', [
            'registro'  => $registro,
            'operacion' => $registro === 'operacion' ? $this->operacion($request) : null,
            'acceso'    => $registro === 'acceso' ? $this->acceso($request) : null,

            'acciones' => collect(AccionOperativa::cases())
                ->map(fn (AccionOperativa $a) => [
                    'valor'    => $a->value,
                    'etiqueta' => $a->etiqueta(),
                    'sensible' => $a->tocaDatosPersonales(),
                ])
                ->values(),

            'tipos' => collect(EventoAuth::cases())
                ->map(fn (EventoAuth $t) => ['valor' => $t->value, 'etiqueta' => $t->etiqueta()])
                ->values(),

            'filtros' => $request->only(['accion', 'buscar', 'tipo', 'correo', 'fallos', 'desde', 'hasta']),
        ]);
    }

    private function operacion(Request $request)
    {
        return EntradaBitacora::query()
            ->with(['actor:id,name', 'sujeto:id,name'])
            ->when($request->string('accion')->toString(), fn ($q, $a) => $q->where('accion', $a))
            ->when($request->string('buscar')->toString(), function ($q, $texto) {
                $like = '%' . $texto . '%';
                $q->where(fn ($s) => $s
                    ->where('descripcion', 'like', $like)
                    ->orWhere('motivo', 'like', $like)
                    ->orWhereHas('sujeto', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('actor', fn ($u) => $u->where('name', 'like', $like)));
            })
            ->when($request->string('desde')->toString(), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->string('hasta')->toString(), fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            ->recientes()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (EntradaBitacora $e) => [
                'id'          => $e->id,
                'accion'      => $e->accion,
                'etiqueta'    => $e->etiqueta,
                'sensible'    => $e->accion_enum?->tocaDatosPersonales() ?? false,
                'descripcion' => $e->descripcion,
                'motivo'      => $e->motivo,
                'actor'       => $e->actor?->name,
                'sujeto'      => $e->sujeto?->only(['id', 'name']),
                'sujeto_url'  => $e->sujeto_user_id ? route('miembros.show', $e->sujeto_user_id) : null,
                'contexto'    => $e->contexto,
                'ip'          => $e->ip,
                'cuando'      => $e->created_at?->toIso8601String(),
            ]);
    }

    private function acceso(Request $request)
    {
        return EventoAutenticacion::query()
            ->with('usuario:id,name,email')
            ->when($request->string('tipo')->toString(), fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->string('correo')->toString(), fn ($q, $c) => $q->where('correo', 'like', '%' . $c . '%'))
            ->when($request->boolean('fallos'), fn ($q) => $q->where('exito', false))
            ->recientes()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (EventoAutenticacion $e) => [
                'id'       => $e->id,
                'etiqueta' => $e->etiqueta,
                'tipo'     => $e->tipo,
                'exito'    => $e->exito,
                'delicado' => $e->evento?->esDelicado() ?? false,
                'usuario'  => $e->usuario?->only(['id', 'name', 'email']),
                'correo'   => $e->correo,
                'ip'       => $e->ip,
                'agente'   => $e->agente,
                'contexto' => $e->contexto,
                'cuando'   => $e->created_at?->toIso8601String(),
            ]);
    }
}
