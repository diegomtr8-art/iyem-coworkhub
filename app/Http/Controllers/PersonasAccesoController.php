<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\CategoriaPersonaAcceso;
use App\Models\EntradaBitacora;
use App\Models\PersonaAcceso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los tres listados de personas con acceso: miembros, empleados y servicio social.
 *
 * Los miembros ya son usuarios de Nódico (aquí solo se ven y se les amarra el
 * rostro). Empleados y prestadores de servicio social son fichas propias con
 * alta/edición/baja. El rostro de Smart Pass (FaceID) es único en todo el
 * sistema: no puede estar a la vez en un miembro y en una ficha.
 */
class PersonasAccesoController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar', ''));

        $miembros = User::query()
            ->miembros()
            ->when($buscar, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$buscar}%")
                ->orWhere('email', 'like', "%{$buscar}%")))
            ->orderBy('name')
            ->paginate(20, ['id', 'name', 'email', 'smartpass_person_id'])
            ->withQueryString()
            ->through(fn (User $u) => [
                'id'         => $u->id,
                'nombre'     => $u->name,
                'correo'     => $u->email,
                'person_id'  => $u->smartpass_person_id,
            ]);

        return Inertia::render('Accesos/Personas', [
            'miembros'       => $miembros,
            'buscar'         => $buscar,
            'empleados'      => $this->fichas(CategoriaPersonaAcceso::Empleado),
            'servicioSocial' => $this->fichas(CategoriaPersonaAcceso::ServicioSocial),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        PersonaAcceso::create($datos);

        return back()->with('success', "{$datos['nombre']} quedó registrado.");
    }

    public function update(Request $request, PersonaAcceso $persona): RedirectResponse
    {
        $persona->update($this->validar($request, $persona));

        return back()->with('success', "{$persona->nombre} se actualizó.");
    }

    public function destroy(PersonaAcceso $persona): RedirectResponse
    {
        $nombre = $persona->nombre;
        $persona->delete();

        return back()->with('success', "{$nombre} se eliminó del listado.");
    }

    /**
     * Amarra un rostro de Smart Pass a un miembro o a una ficha de persona.
     * El `person_id` es único en TODO el sistema (usuarios y fichas).
     */
    public function vincular(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo'      => ['required', Rule::in(['miembro', 'persona'])],
            'id'        => ['required', 'integer'],
            'person_id' => ['required', 'integer', 'min:1'],
        ]);

        $personId = (int) $datos['person_id'];

        // ¿El rostro ya es de alguien más? (otro miembro o cualquier ficha)
        $ocupadoUser = User::where('smartpass_person_id', $personId)
            ->when($datos['tipo'] === 'miembro', fn ($q) => $q->whereKeyNot($datos['id']))
            ->exists();
        $ocupadoFicha = PersonaAcceso::where('smartpass_person_id', $personId)
            ->when($datos['tipo'] === 'persona', fn ($q) => $q->whereKeyNot($datos['id']))
            ->exists();

        if ($ocupadoUser || $ocupadoFicha) {
            return back()->with('error', 'Ese rostro ya está vinculado a otra persona.');
        }

        if ($datos['tipo'] === 'miembro') {
            $sujeto = User::findOrFail($datos['id']);
            $sujeto->forceFill(['smartpass_person_id' => $personId])->save();
            $nombre = $sujeto->name;
        } else {
            $sujeto = PersonaAcceso::findOrFail($datos['id']);
            $sujeto->forceFill(['smartpass_person_id' => $personId])->save();
            $nombre = $sujeto->nombre;
        }

        EntradaBitacora::registrar(
            accion: AccionOperativa::VinculacionRostro,
            descripcion: "Vinculó el rostro (persona {$personId} de Smart Pass) a {$nombre}.",
            actor: $request->user(),
            sujeto: $datos['tipo'] === 'miembro' ? $sujeto : null,
            contexto: ['smartpass_person_id' => $personId, 'tipo' => $datos['tipo']],
        );

        return back()->with('success', "{$nombre} quedó vinculado al rostro {$personId}.");
    }

    public function desvincular(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['miembro', 'persona'])],
            'id'   => ['required', 'integer'],
        ]);

        $sujeto = $datos['tipo'] === 'miembro'
            ? User::findOrFail($datos['id'])
            : PersonaAcceso::findOrFail($datos['id']);

        $sujeto->forceFill(['smartpass_person_id' => null])->save();

        return back()->with('success', 'Rostro desvinculado.');
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function fichas(CategoriaPersonaAcceso $categoria): array
    {
        return PersonaAcceso::deCategoria($categoria)
            ->orderBy('activo', 'desc')->orderBy('nombre')
            ->get()
            ->map(fn (PersonaAcceso $p) => [
                'id'            => $p->id,
                'nombre'        => $p->nombre,
                'correo'        => $p->correo,
                'telefono'      => $p->telefono,
                'identificador' => $p->identificador,
                'puesto'        => $p->puesto,
                'inicio'        => $p->inicio?->toDateString(),
                'fin'           => $p->fin?->toDateString(),
                'activo'        => $p->activo,
                'notas'         => $p->notas,
                'person_id'     => $p->smartpass_person_id,
            ])->all();
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?PersonaAcceso $persona = null): array
    {
        return $request->validate([
            'categoria'     => ['required', Rule::in(CategoriaPersonaAcceso::valores())],
            'nombre'        => ['required', 'string', 'max:255'],
            'correo'        => ['nullable', 'email', 'max:255'],
            'telefono'      => ['nullable', 'string', 'max:30'],
            'identificador' => ['nullable', 'string', 'max:60'],
            'puesto'        => ['nullable', 'string', 'max:255'],
            'inicio'        => ['nullable', 'date'],
            'fin'           => ['nullable', 'date', 'after_or_equal:inicio'],
            'activo'        => ['boolean'],
            'notas'         => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
