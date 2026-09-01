<?php

namespace App\Models;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Orden de pago con referencia (transferencia / efectivo).
 *
 * El centro del flujo que convive con Stripe. Guarda una **foto** de los datos
 * fiscales del miembro (no un enlace vivo): si cambia su RFC después, la orden
 * conserva con qué se pactó. Estado del pago y estado de la factura van en
 * columnas separadas, porque son dos ciclos distintos.
 */
class OrdenPago extends Model
{
    use HasFactory;

    protected $table = 'ordenes_pago';

    protected $fillable = [
        'referencia', 'referencia_normalizada', 'user_id', 'plan_id', 'monto',
        'metodo', 'estado_pago', 'estado_factura', 'pide_factura', 'vence_el',
        'confirmada_por_user_id', 'confirmada_en', 'fecha_pago', 'monto_recibido',
        'evidencia', 'nota', 'motivo_cancelacion', 'suscripcion_id',
        'fiscal_rfc', 'fiscal_razon_social', 'fiscal_regimen', 'fiscal_uso_cfdi',
        'fiscal_cp', 'fiscal_email',
        'folio_fiscal', 'factura_pdf', 'factura_xml', 'factura_emitida_en', 'factura_enviada_en',
    ];

    /** Una orden nace pendiente de pago y sin factura, también en memoria. */
    protected $attributes = [
        'estado_pago'    => 'generada',
        'estado_factura' => 'no_solicitada',
        'pide_factura'   => false,
    ];

    protected $casts = [
        'metodo'             => MetodoReferencia::class,
        'estado_pago'        => EstadoPagoOrden::class,
        'estado_factura'     => EstadoFacturaOrden::class,
        'pide_factura'       => 'boolean',
        'monto'              => 'decimal:2',
        'monto_recibido'     => 'decimal:2',
        'vence_el'           => 'datetime',
        'confirmada_en'      => 'datetime',
        'fecha_pago'         => 'date',
        'factura_emitida_en' => 'datetime',
        'factura_enviada_en' => 'datetime',
    ];

    // ── Relaciones ───────────────────────────────────────────────────────────

    public function user()          { return $this->belongsTo(User::class); }
    public function plan()          { return $this->belongsTo(Plane::class, 'plan_id'); }
    public function suscripcion()   { return $this->belongsTo(Suscripcion::class); }
    public function confirmadaPor() { return $this->belongsTo(User::class, 'confirmada_por_user_id'); }

    // ── Referencia ───────────────────────────────────────────────────────────

    /**
     * Alfabeto sin caracteres que se confundan al dictar en caja o al leer un
     * concepto bancario: fuera `0`/`O`, `1`/`I`/`L`.
     */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Una referencia nueva y única, como `NDC-7K4M2Q`. Devuelve el par
     * [mostrar, normalizada] para guardar ambas.
     *
     * @return array{referencia: string, referencia_normalizada: string}
     */
    public static function nuevaReferencia(): array
    {
        do {
            $cuerpo = '';
            for ($i = 0; $i < 6; $i++) {
                $cuerpo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            $ref  = 'NDC-' . $cuerpo;
            $norm = self::normalizar($ref);
        } while (self::where('referencia_normalizada', $norm)->exists());

        return ['referencia' => $ref, 'referencia_normalizada' => $norm];
    }

    /**
     * Normaliza para comparar: mayúsculas y solo letras/dígitos. Así `ndc-7k4m2q`,
     * `NDC 7K4M2Q` y `NDC7K4M2Q` caen todos en `NDC7K4M2Q` — que es como los
     * bancos suelen devolver el concepto.
     */
    public static function normalizar(string $texto): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto) ?? '');
    }

    /** Busca por referencia tolerando el formato en que venga (banco, dictado…). */
    public function scopeBuscarReferencia($query, string $texto)
    {
        return $query->where('referencia_normalizada', self::normalizar($texto));
    }

    // ── Foto fiscal (Fase 1.3) ───────────────────────────────────────────────

    /** Copia los datos fiscales dentro de la orden, para que no cambien después. */
    public function copiarFiscalesDe(?DatosFiscales $df): void
    {
        if (! $df) {
            return;
        }
        $this->fiscal_rfc          = $df->rfc;
        $this->fiscal_razon_social = $df->razon_social;
        $this->fiscal_regimen      = $df->regimen_fiscal;
        $this->fiscal_uso_cfdi     = $df->uso_cfdi;
        $this->fiscal_cp           = $df->codigo_postal;
        $this->fiscal_email        = $df->email_facturacion;
    }

    // ── Estado ───────────────────────────────────────────────────────────────

    /** Está viva y ya pasó su fecha: candidata a marcarse vencida. */
    public function estaVencida(): bool
    {
        return $this->estado_pago === EstadoPagoOrden::Generada
            && $this->vence_el !== null
            && $this->vence_el->isPast();
    }
}
