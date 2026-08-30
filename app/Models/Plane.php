<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una membresía de Nódico.
 *
 * ## Qué significa `null` en cada campo — leer antes de tocar nada
 *
 * Esta tabla usa `null` con dos sentidos opuestos, y confundirlos fue el
 * BUG-02. Quedan fijados aquí y no se interpretan en ningún otro sitio:
 *
 * | Campo | `null` significa |
 * |---|---|
 * | `dias_cowork_mes` | **coworking ilimitado** (Nodo Pro, Nodo Match) |
 * | `horas_sala_mes` | **el plan no incluye salas** (Day-Pass, Flex) |
 * | `horas_contenido_mes` | el plan no incluye sala de contenido |
 * | `horas_asesoria_mes` | el plan no incluye asesoría IYEM |
 * | `max_horas_sala_dia` | sin tope diario, solo el cupo del ciclo |
 * | `max_horas_contenido_dia` | sin tope diario |
 * | `max_horas_asesoria_dia` | sin tope diario |
 *
 * En resumen: en `dias_cowork_mes`, `null` es *más*; en las bolsas de horas,
 * `null` es *nada*. Quien necesite el saldo no debe leer estos campos a mano:
 * `BolsaDeHoras` los resuelve con ese criterio ya aplicado.
 */
class Plane extends Model
{
    use HasFactory;

    protected $table = 'planes';

    /**
     * `incluye_sala_juntas` **ya no está**: era un segundo campo gobernando la
     * misma regla que `horas_sala_mes` y podían contradecirse (BUG-02). Se
     * eliminó de la tabla y ahora se deriva; ver `incluyeSalaJuntas()`.
     */
    protected $fillable = [
        'nombre', 'subtitulo', 'tipo', 'precio',
        'horas_incluidas', 'dias_cowork_mes',
        'horas_sala_mes', 'horas_contenido_mes', 'horas_asesoria_mes',
        'max_horas_sala_dia', 'max_horas_contenido_dia', 'max_horas_asesoria_dia',
        'personas',
        'max_reservas_mes', 'acceso_24h',
        'color', 'destacado', 'activo',
        'stripe_url', 'stripe_price_id', 'cobro_recurrente',
        'beneficios', 'descripcion_corta', 'descripcion_larga',
        'periodo_label', 'cta_label', 'imagen', 'orden',
    ];

    protected $casts = [
        'precio'     => 'float',
        'acceso_24h' => 'boolean',
        'destacado'  => 'boolean',
        'activo'     => 'boolean',
        'cobro_recurrente' => 'boolean',
        'beneficios' => 'array',
    ];

    protected $appends = ['incluye_sala_juntas'];

    public function scopePublicos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('precio');
    }

    public function suscripciones() { return $this->hasMany(Suscripcion::class, 'plan_id'); }

    // ── Qué incluye el plan ─────────────────────────────────────────────────

    /** Coworking sin límite de días. */
    public function esIlimitado(): bool
    {
        return is_null($this->dias_cowork_mes);
    }

    public function tieneAccesoSala(): bool
    {
        return BolsaDeHoras::Sala->incluidaEn($this);
    }

    public function tieneAccesoContenido(): bool
    {
        return BolsaDeHoras::Contenido->incluidaEn($this);
    }

    public function tieneAccesoAsesoria(): bool
    {
        return BolsaDeHoras::Asesoria->incluidaEn($this);
    }

    /**
     * Accesor derivado que sustituye a la columna borrada.
     *
     * `create` decidía qué espacios enseñar con `incluye_sala_juntas` mientras
     * `store` validaba el cupo con `horas_sala_mes`: dos campos para una sola
     * regla, y nada impedía que un plan tuviera uno sin el otro. La bolsa es la
     * única fuente de verdad, así que esto es exactamente `tieneAccesoSala()`.
     */
    public function incluyeSalaJuntas(): bool
    {
        return $this->tieneAccesoSala();
    }

    public function getIncluyeSalaJuntasAttribute(): bool
    {
        return $this->incluyeSalaJuntas();
    }

    /**
     * Si las bolsas se reinician cada mes en el aniversario de la suscripción.
     *
     * Nodo Pro y Nodo Match son mensuales: quien contrata el 15 de marzo tiene
     * sus horas del 15 de marzo al 14 de abril, y lo no usado se pierde.
     *
     * Day-Pass y Nódico Flex **no** tienen ciclos: se compran con una ventana
     * de 30 días para consumir su día o sus cuatro días, y al agotarse la
     * ventana se acabó la membresía. Reiniciarles la bolsa cada mes les
     * regalaría días.
     */
    public function tieneCiclosMensuales(): bool
    {
        return $this->tipo === 'mes';
    }

    /** Bolsas que este plan incluye, para pintar los medidores del portal. */
    public function bolsasIncluidas(): array
    {
        return array_values(array_filter(
            BolsaDeHoras::cases(),
            fn (BolsaDeHoras $bolsa) => $bolsa->incluidaEn($this)
        ));
    }
}
