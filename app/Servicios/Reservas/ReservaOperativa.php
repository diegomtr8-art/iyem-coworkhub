<?php

namespace App\Servicios\Reservas;

use App\Enums\AccionOperativa;
use App\Enums\MotivoMovimiento;
use App\Models\EntradaBitacora;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reservas hechas desde el mostrador (Fase 3.4).
 *
 * Comparte con el portal el validador de calendario y el registro de bloques:
 * el horario, los festivos y el traslape valen igual venga la reserva de donde
 * venga. Lo que cambia es una sola cosa:
 *
 * **Recepción puede reservar por encima del cupo de un miembro.** A veces hay
 * que resolver —el cliente está enfrente, el asunto es urgente, la sala está
 * vacía—. Pero eso no puede ser silencioso: exige confirmación explícita, un
 * motivo escrito y queda en la bitácora con el nombre de quien lo autorizó.
 * Un sobrecupo que nadie firma es dinero que Nódico regala sin enterarse.
 *
 * Lo que recepción **no** puede saltarse es el calendario: si el local está
 * cerrado, está cerrado; y si la sala está ocupada, sigue ocupada.
 */
class ReservaOperativa
{
    public function __construct(
        private readonly ValidadorDeReserva $calendario,
        private readonly RegistroDeBloques $bloques,
        private readonly LibroDeHoras $libro,
    ) {
    }

    /**
     * @param  bool  $autorizaSobrecupo  Confirmación explícita de quien opera.
     *         Sin ella, pasarse del cupo se rechaza igual que en el portal.
     *
     * @return array{reserva: Reserva, sobrecupo: bool}
     */
    public function crear(
        User $miembro,
        Espacio $espacio,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        User $operativo,
        bool $autorizaSobrecupo = false,
        ?string $motivoSobrecupo = null,
    ): array {
        if (! $espacio->esReservablePorMiembro()) {
            throw ValidationException::withMessages([
                'espacio_id' => 'Ese espacio no se reserva. El coworking es de acceso libre y los '
                    . 'salones se gestionan desde Eventos.',
            ]);
        }

        // Recepción se salta la antelación mínima, no el horario ni los festivos.
        $this->calendario->validar(
            espacio: $espacio,
            fecha: $fecha,
            horaInicio: $horaInicio,
            horaFin: $horaFin,
            comoOperativo: true,
        );

        $suscripcion = $miembro->suscripciones()
            ->with('plan')
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();

        if (! $suscripcion) {
            throw ValidationException::withMessages([
                'user_id' => $miembro->name . ' no tiene una membresía activa.',
            ]);
        }

        $bolsa = $espacio->bolsa();
        $horas = Reserva::calcularHoras($horaInicio, $horaFin);

        // Con la fecha: sin ella el tope diario no se comprueba.
        $exceso = $this->calcularExceso($suscripcion, $bolsa, $horas, $fecha);

        if ($exceso !== null && ! $autorizaSobrecupo) {
            throw ValidationException::withMessages([
                'sobrecupo' => $exceso,
            ]);
        }

        if ($exceso !== null && trim((string) $motivoSobrecupo) === '') {
            throw ValidationException::withMessages([
                'motivo_sobrecupo' => 'Escribe por qué autorizas el sobrecupo. Queda en la bitácora con tu nombre.',
            ]);
        }

        return DB::transaction(function () use (
            $miembro, $espacio, $fecha, $horaInicio, $horaFin, $operativo,
            $suscripcion, $bolsa, $horas, $exceso, $motivoSobrecupo
        ) {
            Reserva::where('espacio_id', $espacio->id)
                ->whereDate('fecha', $fecha)
                ->lockForUpdate()
                ->get();

            $ocupado = Reserva::where('espacio_id', $espacio->id)
                ->whereDate('fecha', $fecha)
                ->confirmadas()
                ->where('hora_inicio', '<', $horaFin)
                ->where('hora_fin', '>', $horaInicio)
                ->exists();

            if ($ocupado) {
                throw ValidationException::withMessages([
                    'hora_inicio' => "{$espacio->nombre} ya está ocupada en ese horario.",
                ]);
            }

            $reserva = Reserva::create([
                'user_id'        => $miembro->id,
                'espacio_id'     => $espacio->id,
                'suscripcion_id' => $suscripcion->id,
                'fecha'          => $fecha,
                'hora_inicio'    => $horaInicio,
                'hora_fin'       => $horaFin,
                'estatus'        => 'Confirmada',
                'precio_total'   => 0,
            ]);

            try {
                $this->bloques->ocupar($reserva);
            } catch (QueryException $e) {
                throw ValidationException::withMessages([
                    'hora_inicio' => 'Alguien acaba de tomar ese horario. Vuelve a intentarlo.',
                ]);
            }

            $this->libro->registrar(
                suscripcion: $suscripcion,
                bolsa: $bolsa,
                cantidad: $horas,
                motivo: MotivoMovimiento::Reserva,
                reserva: $reserva,
                autor: $operativo,
                nota: $exceso !== null
                    ? 'Reserva de mostrador con sobrecupo autorizado: ' . $motivoSobrecupo
                    : 'Reserva hecha desde recepción.',
            );

            EntradaBitacora::registrar(
                accion: AccionOperativa::ReservaOperativa,
                descripcion: sprintf(
                    'Reservó %s para %s el %s de %s a %s.',
                    $espacio->nombre,
                    $miembro->name,
                    $fecha,
                    substr($horaInicio, 0, 5),
                    substr($horaFin, 0, 5),
                ),
                actor: $operativo,
                sujeto: $miembro,
                contexto: ['reserva_id' => $reserva->id, 'horas' => $horas],
            );

            if ($exceso !== null) {
                EntradaBitacora::registrar(
                    accion: AccionOperativa::Sobrecupo,
                    descripcion: "Sobrecupo autorizado para {$miembro->name} en {$espacio->nombre}: {$exceso}",
                    actor: $operativo,
                    sujeto: $miembro,
                    motivo: $motivoSobrecupo,
                    contexto: [
                        'reserva_id' => $reserva->id,
                        'bolsa'      => $bolsa->value,
                        'horas'      => $horas,
                        'detalle'    => $exceso,
                    ],
                );
            }

            return ['reserva' => $reserva, 'sobrecupo' => $exceso !== null];
        });
    }

    /**
     * Comprueba cupo y tope diario **sin lanzar**, y devuelve el motivo del
     * exceso o `null` si cabe.
     *
     * Devolver el texto en vez de una excepción es lo que permite a la pantalla
     * avisar del sobrecupo y pedir la autorización, en vez de rechazar sin más.
     */
    public function calcularExceso(
        Suscripcion $suscripcion,
        \App\Enums\BolsaDeHoras $bolsa,
        float $horas,
        ?string $fecha = null,
    ): ?string {
        $saldo = $this->libro->saldoDelCiclo($suscripcion, $bolsa);

        if ($saldo !== null && $saldo < $horas) {
            return "le quedan {$saldo} h de {$bolsa->etiqueta()} y esta reserva son {$horas} h";
        }

        $tope = $bolsa->topeDiario($suscripcion->plan);

        if ($tope !== null && $fecha !== null) {
            $eseDia = Reserva::where('suscripcion_id', $suscripcion->id)
                ->whereDate('fecha', $fecha)
                ->confirmadas()
                ->whereHas('espacio', fn ($q) => $q->deBolsa($bolsa))
                ->get()
                ->sum(fn (Reserva $r) => $r->duracionEnHoras());

            if ($eseDia + $horas > $tope) {
                return "su plan permite {$tope} h al día y ese día ya tiene {$eseDia} h";
            }
        }

        return null;
    }
}
