<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\EntradaBitacora;
use App\Models\User;
use App\Servicios\Accesos\Credenciales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recepción valida la credencial QR de la app (decisión del 22/09/2026).
 *
 * Un lector de códigos USB o Bluetooth «teclea» el código en el campo y pulsa
 * Enter, así que la pantalla es un formulario de un solo campo. La respuesta es
 * un semáforo con foto, nombre, plan y vigencia. No abre el torno: identifica.
 * Cada validación queda en la bitácora.
 */
class CredencialPanelController extends Controller
{
    public function __construct(private readonly Credenciales $credenciales)
    {
    }

    public function mostrar(): Response
    {
        return Inertia::render('Accesos/Credencial', ['resultado' => null]);
    }

    public function validar(Request $request): Response
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:120']]);

        $resultado = $this->credenciales->validar($datos['codigo']);

        if ($resultado['encontrada']) {
            $resultado['persona']['avatar_url'] = UsuarioMovil::urlPublica($resultado['persona']['avatar']);
            unset($resultado['persona']['avatar']);

            EntradaBitacora::registrar(
                accion: AccionOperativa::ValidacionCredencial,
                descripcion: 'Credencial validada en recepción: ' . $resultado['semaforo'] . '.',
                actor: $request->user(),
                sujeto: User::find($resultado['persona']['id']),
                contexto: ['semaforo' => $resultado['semaforo'], 'motivo' => $resultado['motivo']],
            );

            $resultado['persona']['url'] = route('miembros.show', $resultado['persona']['id']);
        }

        return Inertia::render('Accesos/Credencial', ['resultado' => $resultado]);
    }
}
