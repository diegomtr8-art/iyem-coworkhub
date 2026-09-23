<?php

namespace App\Servicios\Membresias;

use App\Enums\BolsaDeHoras;
use App\Models\Plane;

/**
 * El plan con lo que incluye ya desglosado en frases.
 *
 * Se arma en el servidor porque las reglas de qué significa `null` en cada
 * campo viven en `BolsaDeHoras`, y traducirlas en el front —o en la app— sería
 * una segunda copia de esa semántica. Lo usan «Mi membresía» en la web, la
 * vista previa de planes de la app y la pantalla de contratar.
 */
class DescripcionDelPlan
{
    public function para(?Plane $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        return [
            'id'            => $plan->id,
            'nombre'        => $plan->nombre,
            'subtitulo'     => $plan->subtitulo,
            'precio'        => (float) $plan->precio,
            'periodo_label' => $plan->periodo_label,
            'color'         => $plan->color,
            'personas'      => $plan->personas ?? 1,
            'stripe_url'    => $plan->stripe_url,
            'incluye'       => $this->incluye($plan),

            // Los beneficios editoriales del sitio público, si los tiene.
            'beneficios'    => $plan->beneficios ?? [],
        ];
    }

    /** @return array<int, string> */
    public function incluye(Plane $plan): array
    {
        $incluye = [];

        $incluye[] = $plan->esIlimitado()
            ? 'Acceso ilimitado al coworking'
            : $plan->dias_cowork_mes . ' día' . ($plan->dias_cowork_mes === 1 ? '' : 's') . ' de coworking';

        foreach ([BolsaDeHoras::Sala, BolsaDeHoras::Contenido, BolsaDeHoras::Asesoria] as $bolsa) {
            $cupo = $bolsa->cupo($plan);

            if ($cupo === null) {
                continue;
            }

            $tope  = $bolsa->topeDiario($plan);
            $texto = $this->numero($cupo)
                . ' h de ' . $this->enMinusculas($bolsa->etiqueta())
                . ($plan->tieneCiclosMensuales() ? ' al mes' : '');

            if ($tope !== null) {
                $texto .= ' (máximo ' . $this->numero($tope) . ' h al día'
                    . (($plan->personas ?? 1) > 1 ? ' por persona' : '') . ')';
            }

            $incluye[] = $texto;
        }

        if (($plan->personas ?? 1) > 1) {
            $incluye[] = 'Para ' . $plan->personas . ' personas, con bolsa de horas compartida';
        }

        return $incluye;
    }

    /** «Asesoría IYEM» → «asesoría IYEM»: las siglas se quedan en mayúsculas. */
    private function enMinusculas(string $texto): string
    {
        return preg_replace_callback(
            '/\b(\p{L}+)\b/u',
            fn (array $m) => mb_strtoupper($m[1]) === $m[1] && mb_strlen($m[1]) > 1 ? $m[1] : mb_strtolower($m[1]),
            $texto,
        );
    }

    private function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
