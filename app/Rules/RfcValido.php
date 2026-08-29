<?php

namespace App\Rules;

use App\Models\DatosFiscales;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida la **forma** de un RFC mexicano, no su existencia (Fase 1.3).
 *
 * Aquí se atrapa el dedazo: 12 posiciones para persona moral, 13 para física,
 * seis cifras centrales que sean una fecha real. Validar contra el SAT exige su
 * servicio y no es lo que hace este sistema; contabilidad del IYEM valida de
 * verdad al timbrar. Prometer más de esto sería mentirle al miembro.
 */
class RfcValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rfc = DatosFiscales::normalizarRfc((string) $value);

        if ($rfc === '') {
            $fail('Escribe tu RFC.');

            return;
        }

        if (! in_array(mb_strlen($rfc), [12, 13], true)) {
            $fail('El RFC debe tener 12 posiciones si eres persona moral o 13 si eres persona física. '
                . 'El que escribiste tiene ' . mb_strlen($rfc) . '.');

            return;
        }

        if (! DatosFiscales::rfcTieneFormatoValido($rfc)) {
            $fail('Ese RFC no tiene un formato válido. Revisa que las letras y la fecha estén bien: '
                . 'son letras del nombre, luego la fecha en AAMMDD y al final la homoclave.');
        }
    }
}
