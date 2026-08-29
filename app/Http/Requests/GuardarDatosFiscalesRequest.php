<?php

namespace App\Http\Requests;

use App\Models\DatosFiscales;
use App\Rules\RfcValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de datos fiscales (Fase 1.3).
 *
 * El régimen y el uso de CFDI se validan **contra el catálogo del SAT**
 * (`config/sat.php`), no como texto libre: contabilidad del IYEM necesita la
 * clave exacta, y un «RESICO» escrito a mano obliga a alguien a adivinar si eso
 * era 626 o 621.
 */
class GuardarDatosFiscalesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rfc'               => ['required', 'string', 'max:20', new RfcValido()],
            'razon_social'      => ['required', 'string', 'max:255'],
            'regimen_fiscal'    => ['required', 'string', Rule::in(array_keys(config('sat.regimenes')))],
            'uso_cfdi'          => ['required', 'string', Rule::in(array_keys(config('sat.usos_cfdi')))],
            'codigo_postal'     => ['required', 'string', 'regex:/^\d{5}$/'],
            'email_facturacion' => ['required', 'email:rfc,dns', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'razon_social.required'      => 'Escribe tu nombre o razón social, tal como está en tu constancia de situación fiscal.',
            'regimen_fiscal.in'          => 'Elige un régimen fiscal del catálogo.',
            'uso_cfdi.in'                => 'Elige un uso de CFDI del catálogo.',
            'codigo_postal.regex'        => 'El código postal son 5 dígitos.',
            'email_facturacion.required' => 'Hace falta un correo al que enviar la factura.',
        ];
    }

    /**
     * Coherencia entre el RFC y el régimen.
     *
     * Un RFC de 12 posiciones es persona moral y no puede acogerse a un régimen
     * que solo existe para personas físicas. Se comprueba porque es el error
     * que más veces devuelve contabilidad, y devolverlo cuesta días.
     */
    public function after(): array
    {
        return [
            function (Validator $validador) {
                $rfc     = DatosFiscales::normalizarRfc((string) $this->input('rfc'));
                $regimen = (string) $this->input('regimen_fiscal');

                if (! DatosFiscales::rfcTieneFormatoValido($rfc) || $regimen === '') {
                    return;
                }

                $permitidos = config("sat.regimenes.{$regimen}.personas", []);

                if ($permitidos === []) {
                    return;
                }

                $tipo = mb_strlen($rfc) === 12 ? 'moral' : 'fisica';

                if (! in_array($tipo, $permitidos, true)) {
                    $nombre = config("sat.regimenes.{$regimen}.nombre", $regimen);
                    $eres   = $tipo === 'moral' ? 'persona moral' : 'persona física';

                    $validador->errors()->add(
                        'regimen_fiscal',
                        "Tu RFC es de {$eres} y «{$nombre}» no aplica a ese tipo de contribuyente. "
                        . 'Revisa tu constancia de situación fiscal.'
                    );
                }
            },
        ];
    }

    /** El RFC se normaliza también aquí para que el mensaje de error lo enseñe ya limpio. */
    protected function prepareForValidation(): void
    {
        if ($this->has('rfc')) {
            $this->merge(['rfc' => DatosFiscales::normalizarRfc((string) $this->input('rfc'))]);
        }
    }
}
