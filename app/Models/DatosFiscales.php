<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos fiscales de un miembro (Fase 1.3).
 *
 * **Dato personal sensible.** El acceso va por `DatosFiscalesPolicy` y toda
 * lectura o cambio queda en la bitácora; ver `PoliticaDeDatosFiscales`.
 *
 * Nódico no timbra CFDI: esto se recopila y se le entrega a contabilidad del
 * IYEM, que es quien emite.
 */
class DatosFiscales extends Model
{
    use HasFactory;

    protected $table = 'datos_fiscales';

    protected $fillable = [
        'user_id', 'rfc', 'razon_social', 'regimen_fiscal',
        'uso_cfdi', 'codigo_postal', 'email_facturacion', 'actualizado_por_user_id',
    ];

    public function user()            { return $this->belongsTo(User::class); }
    public function actualizadoPor()  { return $this->belongsTo(User::class, 'actualizado_por_user_id'); }

    /**
     * El RFC se normaliza **al escribir**, no al leer ni al validar.
     *
     * Puesto en el modelo y no en el formulario, cualquier vía de escritura
     * —un seeder, un comando, una importación de contabilidad— guarda el mismo
     * formato. Si la normalización viviera solo en el `FormRequest`, bastaría
     * un `DatosFiscales::create()` desde otro sitio para meter un RFC con
     * guiones y partir las búsquedas por RFC.
     */
    protected function rfc(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => self::normalizarRfc((string) $valor),
        );
    }

    public static function normalizarRfc(string $rfc): string
    {
        // `mb_strtoupper` y no `strtoupper`: este último no sabe de UTF-8 y
        // dejaría la «ñ» minúscula, con lo que el patrón de validación
        // —que espera «Ñ»— rechazaría un RFC perfectamente correcto.
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9Ññ&]/u', '', $rfc) ?? '', 'UTF-8');
    }

    /**
     * Comprueba la **forma** del RFC, no su existencia.
     *
     * Validar contra el SAT exige su servicio y no es lo que este sistema hace:
     * aquí se atrapa el dedazo, no el fraude. Contabilidad del IYEM valida de
     * verdad al timbrar.
     *
     * - Persona moral: 3 letras + 6 dígitos de fecha + 3 de homoclave = 12.
     * - Persona física: 4 letras + 6 dígitos de fecha + 3 de homoclave = 13.
     */
    public static function rfcTieneFormatoValido(string $rfc): bool
    {
        $rfc = self::normalizarRfc($rfc);

        // `mb_strlen`, no `strlen`: la Ñ ocupa dos bytes en UTF-8 y un RFC
        // como «ÑAXX010101000» mide 13 caracteres pero 14 bytes. Con `strlen`
        // se rechazaría a todo contribuyente cuyo apellido empiece por Ñ.
        return (bool) preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)
            && in_array(mb_strlen($rfc), [12, 13], true)
            && self::fechaDelRfcEsPlausible($rfc);
    }

    /**
     * Las seis cifras centrales son AAMMDD. Un `130229` o un `131301` es un
     * dedazo, y es el error más común al teclear un RFC.
     */
    private static function fechaDelRfcEsPlausible(string $rfc): bool
    {
        $inicio = mb_strlen($rfc) === 12 ? 3 : 4;
        $fecha  = mb_substr($rfc, $inicio, 6);

        $anio = (int) mb_substr($fecha, 0, 2);
        $mes  = (int) mb_substr($fecha, 2, 2);
        $dia  = (int) mb_substr($fecha, 4, 2);

        // El año de dos cifras es ambiguo por definición; se acepta cualquiera.
        return checkdate($mes, $dia, $anio < 30 ? 2000 + $anio : 1900 + $anio);
    }

    public function esPersonaMoral(): bool
    {
        return mb_strlen((string) $this->rfc) === 12;
    }

    public function tipoDePersona(): string
    {
        return $this->esPersonaMoral() ? 'moral' : 'fisica';
    }

    public function regimenLegible(): string
    {
        return config("sat.regimenes.{$this->regimen_fiscal}.nombre", $this->regimen_fiscal);
    }

    public function usoCfdiLegible(): string
    {
        return config("sat.usos_cfdi.{$this->uso_cfdi}", $this->uso_cfdi);
    }

    /**
     * Si están completos para pedir factura. Todos los campos son obligatorios,
     * así que existir es estar completo; el método está para que la vista no
     * tenga que saberlo y pueda enseñar «completos» o «faltan datos» sin
     * enumerar campos.
     */
    public function estanCompletos(): bool
    {
        foreach (['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal', 'email_facturacion'] as $campo) {
            if (trim((string) $this->{$campo}) === '') {
                return false;
            }
        }

        return self::rfcTieneFormatoValido((string) $this->rfc);
    }
}
