<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'user_id', 'espacio_id', 'suscripcion_id', 'fecha', 'hora_inicio', 'hora_fin', 'estatus', 'precio_total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio_total' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function espacio() { return $this->belongsTo(Espacio::class); }
    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
    public function checkin() { return $this->hasOne(Checkin::class); }

    // ── Duración ────────────────────────────────────────────────────────────

    /**
     * Horas que dura la reserva. **El único sitio del sistema donde se resta
     * una hora de otra** (BUG-01).
     *
     * El código anterior hacía `$fin->diffInMinutes($inicio) / 60` en tres
     * lugares distintos. En Carbon 2 eso devolvía el valor absoluto; en Carbon 3
     * —y este proyecto va con 3.11.4— `diff*` devuelve un valor **con signo**,
     * medido desde el objeto hacia el argumento, así que devolvía **-120**.
     *
     * Con la duración en negativo todo el control de cupos se invertía: las
     * comprobaciones `restantes < $horas` pasaban siempre, `increment()` restaba
     * consumo en vez de sumarlo, `max(0, $horas)` al cancelar daba 0 y el tope
     * diario acumulaba negativos. Reservar regalaba horas.
     *
     * Se mide de inicio a fin, en ese orden, y se comprueba el signo: si alguien
     * vuelve a invertir los operandos, esto revienta en vez de corromper saldos
     * en silencio.
     */
    public static function calcularHoras(string $horaInicio, string $horaFin): float
    {
        $inicio = self::aMinutos($horaInicio);
        $fin    = self::aMinutos($horaFin);

        if ($fin <= $inicio) {
            throw new \InvalidArgumentException(
                "La hora de fin ({$horaFin}) debe ser posterior a la de inicio ({$horaInicio})."
            );
        }

        return round(($fin - $inicio) / 60, 2);
    }

    public function duracionEnHoras(): float
    {
        return self::calcularHoras($this->hora_inicio, $this->hora_fin);
    }

    /**
     * Minutos desde medianoche. Acepta `H:i` y `H:i:s` indistintamente, que es
     * la otra mitad del enredo: el formulario manda `09:00` y la base devuelve
     * `09:00:00`, y el código anterior tenía un `createFromFormat` distinto para
     * cada caso —y reventaba si le llegaba el que no esperaba.
     */
    public static function aMinutos(string $hora): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($hora), $partes)) {
            throw new \InvalidArgumentException("Hora con formato no reconocido: «{$hora}».");
        }

        $horas   = (int) $partes[1];
        $minutos = (int) $partes[2];

        if ($horas > 23 || $minutos > 59) {
            throw new \InvalidArgumentException("Hora fuera de rango: «{$hora}».");
        }

        return $horas * 60 + $minutos;
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    public function scopeConfirmadas($query)
    {
        return $query->where('estatus', 'Confirmada');
    }

    public function estaConfirmada(): bool
    {
        return $this->estatus === 'Confirmada';
    }

    /** Bolsa que consume esta reserva, o `null` si no consume ninguna. */
    public function bolsa(): ?BolsaDeHoras
    {
        return $this->espacio?->tipoEnum()?->bolsa();
    }

    /**
     * Momento en que arranca la reserva, para las reglas que dependen de cuánto
     * falta: la devolución de horas al cancelar y el marcado de no-show.
     */
    public function inicioEnCalendario(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->fecha->toDateString() . ' ' . substr($this->hora_inicio, 0, 5)
        );
    }

    public function finEnCalendario(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $this->fecha->toDateString() . ' ' . substr($this->hora_fin, 0, 5)
        );
    }

    /**
     * Si cancelar ahora devuelve las horas.
     *
     * Decisión de Nódico: **se devuelven con 2 h o más de antelación**. La
     * cancelación tardía y el no-show consumen igual. Vive aquí y no en el
     * controlador porque la pantalla de «Mis reservas» tiene que poder decirlo
     * *antes* de que el miembro pulse el botón: nadie debe descubrir la
     * penalización después de aceptarla.
     */
    public function cancelarDevuelveHoras(?CarbonImmutable $ahora = null): bool
    {
        $ahora = $ahora ?? CarbonImmutable::now();
        $margen = (int) config('nodico.operacion.horas_para_cancelar_sin_penalizacion', 2);

        return $this->inicioEnCalendario()->gte($ahora->addHours($margen));
    }
}
