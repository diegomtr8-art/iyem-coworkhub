<?php

namespace App\Servicios\Horas;

use App\Enums\BolsaDeHoras;
use App\Models\Plane;
use App\Models\Suscripcion;
use Carbon\CarbonImmutable;

/**
 * Arma los medidores de bolsa que ve el miembro.
 *
 * La regla de oro del portal: **en todo momento la persona debe poder responder
 * sin pensar «¿cuánto me queda y hasta cuándo?»**. Eso son dos datos —saldo y
 * fecha de reinicio— y los dos salen de aquí, no de que cada vista eche cuentas
 * por su lado.
 *
 * Incluye a propósito las bolsas que el plan **no** tiene, marcadas como no
 * incluidas y con el plan que sí las trae. Un medidor en cero se lee como un
 * error; «esto lo incluye Nodo Pro» se lee como una oferta.
 */
class ResumenDeBolsas
{
    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function para(Suscripcion $suscripcion, ?CarbonImmutable $en = null): array
    {
        $plan      = $suscripcion->plan;
        $reinicio  = $suscripcion->proximoReinicio($en);
        $medidores = [];

        foreach (BolsaDeHoras::cases() as $bolsa) {
            $medidores[] = $bolsa->incluidaEn($plan)
                ? $this->medidorIncluido($suscripcion, $bolsa, $reinicio, $en)
                : $this->medidorAusente($bolsa);
        }

        return $medidores;
    }

    /** Solo lo que el plan incluye, para las vistas que no ofrecen mejorar. */
    public function soloIncluidas(Suscripcion $suscripcion, ?CarbonImmutable $en = null): array
    {
        return array_values(array_filter(
            $this->para($suscripcion, $en),
            fn (array $medidor) => $medidor['incluida'],
        ));
    }

    private function medidorIncluido(
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        ?CarbonImmutable $reinicio,
        ?CarbonImmutable $en,
    ): array {
        $cupo      = $bolsa->cupo($suscripcion->plan);
        $usado     = $this->libro->consumoDelCiclo($suscripcion, $bolsa, $en);
        $restante  = $this->libro->saldoDelCiclo($suscripcion, $bolsa, $en);
        $ilimitada = $cupo === null;

        return [
            'bolsa'     => $bolsa->value,
            'etiqueta'  => $bolsa->etiqueta(),
            'unidad'    => $bolsa->unidad(),
            'incluida'  => true,
            'ilimitada' => $ilimitada,
            'cupo'      => $cupo,
            'usado'     => $usado,
            'restante'  => $restante,

            // El porcentaje lo calcula el servidor para que la barra y el
            // número no puedan discrepar por un redondeo distinto en el front.
            'porcentaje_usado' => $ilimitada || $cupo <= 0
                ? 0
                : min(100, (int) round($usado / $cupo * 100)),

            'tope_diario'   => $bolsa->topeDiario($suscripcion->plan),
            'reinicia_el'   => $reinicio?->toIso8601String(),
            'reinicia_texto' => $reinicio
                ? 'Se reinicia el ' . $reinicio->translatedFormat('j \d\e F')
                : 'No se reinicia: dura lo que dure tu membresía',

            // Aviso cuando queda poco. El umbral es del servidor por lo mismo
            // que el porcentaje: para que no haya dos criterios de «poco».
            'casi_agotada' => ! $ilimitada && $cupo > 0 && $restante !== null && $restante <= $cupo * 0.2,
            'agotada'      => ! $ilimitada && $restante !== null && $restante <= 0,
        ];
    }

    /**
     * Bolsa que este plan no trae. En vez de un cero, la invitación: qué plan
     * la incluye y cuánto da.
     */
    private function medidorAusente(BolsaDeHoras $bolsa): array
    {
        $mejor = $this->planQueLaIncluye($bolsa);

        return [
            'bolsa'     => $bolsa->value,
            'etiqueta'  => $bolsa->etiqueta(),
            'unidad'    => $bolsa->unidad(),
            'incluida'  => false,
            'ilimitada' => false,
            'cupo'      => null,
            'usado'     => 0.0,
            'restante'  => null,
            'porcentaje_usado' => 0,
            'tope_diario'      => null,
            'reinicia_el'      => null,
            'reinicia_texto'   => null,
            'casi_agotada'     => false,
            'agotada'          => false,

            'sugerencia' => $mejor ? [
                'plan_id'    => $mejor->id,
                'plan'       => $mejor->nombre,
                'precio'     => $mejor->precio,
                'periodo'    => $mejor->periodo_label,
                'incluye'    => $bolsa->cupo($mejor),
                'stripe_url' => $mejor->stripe_url,
            ] : null,
        ];
    }

    /**
     * El plan más barato de los activos que incluye esa bolsa: si vamos a
     * sugerir mejorar, que sea el salto más pequeño que resuelve la falta.
     */
    private function planQueLaIncluye(BolsaDeHoras $bolsa): ?Plane
    {
        return Plane::query()
            ->where('activo', true)
            ->whereNotNull($bolsa->campoCupo())
            ->orderBy('precio')
            ->first();
    }
}
