<?php

namespace App\Servicios\Accesos;

use App\Models\EventoAcceso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Ingesta de los eventos del terminal facial (Fase 2).
 *
 * Recibe un evento ya validado (tal como lo firmó el agente), lo guarda como
 * `EventoAcceso` y, cuando corresponde, deriva un check-in **reutilizando la
 * lógica de `RegistroDeAcceso`** —la misma del mostrador y el portal—, de modo
 * que el consumo de días (BUG-05) se cuenta igual venga de donde venga.
 *
 * Garantías:
 *  - **Idempotente por `origen_id`**: un reintento del agente nunca duplica un
 *    evento, un check-in ni un día. La unicidad la respalda la base de datos.
 *  - **Extraños** (no reconocidos, o person_id/person_type = -1) se guardan como
 *    evento pero jamás generan check-in ni consumen día.
 *  - **Antirrebote**: un reconocimiento repetido del mismo miembro en la misma
 *    dirección dentro de la ventana se guarda, pero no crea un check-in nuevo.
 */
class IngestaDeEventos
{
    /**
     * `direction` del terminal que significa **salida**. El resto de los eventos
     * reconocidos se tratan como entrada. (Smart Pass: 1/2 = entrada/salida.)
     */
    private const DIRECCION_SALIDA = 2;

    public function __construct(private readonly RegistroDeAcceso $accesos)
    {
    }

    /**
     * Procesa un evento. Devuelve un código del resultado, útil para el resumen
     * de la respuesta y para las pruebas.
     *
     * @param  array<string, mixed>  $e
     * @return string  duplicado|extrano|sin_amarre|antirrebote|checkin|salida|sin_checkin
     */
    public function procesar(array $e): string
    {
        $origenId = (int) $e['origen_id'];

        // Fast-path de idempotencia: si ya lo vimos, ni tocamos la base.
        if (EventoAcceso::where('origen_id', $origenId)->exists()) {
            return 'duplicado';
        }

        try {
            return DB::transaction(fn () => $this->registrar($e, $origenId));
        } catch (QueryException $ex) {
            // Carrera: otro envío insertó el mismo origen_id entre el exists() y
            // el insert. La unicidad de la columna lo atrapa: es un duplicado.
            if ($this->esOrigenRepetido($ex)) {
                return 'duplicado';
            }

            throw $ex;
        }
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private function registrar(array $e, int $origenId): string
    {
        $ocurrido   = CarbonImmutable::parse($e['ocurrido_en'])->utc();
        $personId   = (int) $e['person_id'];
        $personType = (int) $e['person_type'];
        $reconocido = (bool) ($e['reconocido'] ?? false);
        $direction  = (int) $e['direction'];

        // Un extraño no se reconoce (o viene con los ids centinela -1).
        $esExtrano = ! $reconocido || $personType === -1 || $personId === -1;

        $user = $esExtrano
            ? null
            : User::where('smartpass_person_id', $personId)->first();

        $evento = EventoAcceso::create([
            'origen_id'      => $origenId,
            'ocurrido_en'    => $ocurrido,
            'person_id'      => $personId,
            'person_type'    => $personType,
            'reconocido'     => $reconocido,
            'pass_type'      => (string) ($e['pass_type'] ?? ''),
            'sub_pass_type'  => $e['sub_pass_type'] ?? null,
            'direction'      => $direction,
            'device_id'      => (int) ($e['device_id'] ?? 0),
            'device_key'     => $e['device_key'] ?? null,
            'user_id'        => $user?->id,
            'genero_checkin' => false,
        ]);

        if ($esExtrano) {
            return 'extrano';
        }

        if (! $user) {
            // Reconocido pero sin amarre en Nódico: se guarda para auditoría, pero
            // no hay a quién hacerle el check-in.
            return 'sin_amarre';
        }

        return $this->derivarCheckin($user, $evento, $direction, $ocurrido);
    }

    /**
     * Deriva el movimiento de acceso a partir de un evento reconocido y amarrado.
     */
    private function derivarCheckin(User $user, EventoAcceso $evento, int $direction, CarbonImmutable $ocurrido): string
    {
        if ($this->esRebote($user, $direction, $ocurrido, $evento->id)) {
            return 'antirrebote';
        }

        if ($direction === self::DIRECCION_SALIDA) {
            $this->accesos->salida($user, $ocurrido);

            return 'salida';
        }

        try {
            $this->accesos->entrada($user, null, $ocurrido);
        } catch (ValidationException $ve) {
            // La puerta ya lo dejó pasar físicamente; Nódico solo lleva el
            // registro. Si su membresía no da para un check-in (vencida o sin
            // días), el evento queda guardado igual, sin check-in.
            Log::info('Acceso: evento reconocido que no derivó en check-in.', [
                'user_id'   => $user->id,
                'origen_id' => $evento->origen_id,
                'motivo'    => $ve->getMessage(),
            ]);

            return 'sin_checkin';
        }

        $evento->update(['genero_checkin' => true]);

        return 'checkin';
    }

    /**
     * ¿Este reconocimiento es un rebote? Lo es si el mismo miembro ya tuvo otro
     * reconocimiento en la misma dirección dentro de la ventana de antirrebote.
     */
    private function esRebote(User $user, int $direction, CarbonImmutable $ocurrido, int $exceptoId): bool
    {
        $ventana = (int) config('acceso.antirrebote_segundos', 90);

        return EventoAcceso::query()
            ->where('user_id', $user->id)
            ->where('direction', $direction)
            ->where('reconocido', true)
            ->where('id', '<', $exceptoId)
            ->whereBetween('ocurrido_en', [$ocurrido->subSeconds($ventana), $ocurrido])
            ->exists();
    }

    private function esOrigenRepetido(QueryException $e): bool
    {
        return (int) ($e->errorInfo[0] ?? 0) === 23000
            && str_contains($e->getMessage(), 'origen_id');
    }
}
