<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un día de cierre del calendario (Fase 1.4).
 *
 * Los festivos **no vienen sembrados**: los de México cambian de fecha cada año
 * por la ley federal del trabajo, y Nódico además cierra días que no son
 * festivos oficiales. Inventar un calendario sería peor que no tener ninguno,
 * porque se confiaría en él. Los captura administración desde el panel.
 */
class DiaFestivo extends Model
{
    use HasFactory;

    protected $table = 'dias_festivos';

    protected $fillable = [
        'fecha', 'nombre', 'cerrado_todo_el_dia', 'hora_apertura', 'hora_cierre',
    ];

    protected $casts = [
        'fecha'               => 'date',
        'cerrado_todo_el_dia' => 'boolean',
    ];

    /**
     * El festivo de una fecha, si lo hay.
     *
     * Sin memoización a propósito. Una `static $memoria` aquí parece gratis
     * —el validador pregunta lo mismo varias veces por petición— pero guarda el
     * resultado durante toda la vida del proceso, y eso convierte un festivo
     * recién dado de alta en invisible para cualquier proceso de cola o de
     * consola que llevara rato levantado. La consulta es por índice único sobre
     * una tabla de unas pocas decenas de filas; si algún día pesa, se cachea
     * bien, con invalidación al guardar.
     */
    public static function enLaFecha(CarbonImmutable $dia): ?self
    {
        return static::whereDate('fecha', $dia->toDateString())->first();
    }

    public function scopeDesde($query, string $fecha)
    {
        return $query->whereDate('fecha', '>=', $fecha)->orderBy('fecha');
    }
}
