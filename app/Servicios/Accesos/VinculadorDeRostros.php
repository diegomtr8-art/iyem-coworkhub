<?php

namespace App\Servicios\Accesos;

use App\Models\ComandoAcceso;
use App\Models\PersonaAcceso;
use App\Models\User;

/**
 * Amarra un rostro de Smart Pass (`person_id`) a un perfil de Nódico.
 *
 * El rostro es único en todo el sistema: no puede estar a la vez en un miembro y
 * en una ficha de persona. `smartpass_person_id` no es asignable en masa, por eso
 * se escribe con `forceFill`.
 */
class VinculadorDeRostros
{
    /** Vincula tras un enrolado exitoso, leyendo a quién del payload del comando. */
    public function vincularDesdeEnrolado(ComandoAcceso $orden, int $personId): void
    {
        $tipo = $orden->payload['perfil_tipo'] ?? null;
        $id   = $orden->payload['perfil_id'] ?? null;

        if ($tipo && $id) {
            $this->vincular((string) $tipo, (int) $id, $personId);
        }
    }

    /** Vincula un person_id a un perfil (miembro o ficha), si el rostro está libre. */
    public function vincular(string $tipo, int $id, int $personId): bool
    {
        $ocupadoUser = User::where('smartpass_person_id', $personId)
            ->when($tipo === 'miembro', fn ($q) => $q->whereKeyNot($id))->exists();
        $ocupadoFicha = PersonaAcceso::where('smartpass_person_id', $personId)
            ->when($tipo === 'persona', fn ($q) => $q->whereKeyNot($id))->exists();

        if ($ocupadoUser || $ocupadoFicha) {
            return false;
        }

        $sujeto = $tipo === 'miembro' ? User::find($id) : PersonaAcceso::find($id);
        $sujeto?->forceFill(['smartpass_person_id' => $personId])->save();

        return $sujeto !== null;
    }
}
