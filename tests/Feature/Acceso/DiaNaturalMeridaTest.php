<?php

namespace Tests\Feature\Acceso;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Enums\TipoEspacio;
use App\Models\Espacio;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Accesos\RegistroDeAcceso;
use App\Servicios\Membresias\GestorDeMembresias;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El día natural del coworking se cuenta en hora de Mérida (UTC-6), no en UTC.
 *
 * El caso que motivó el cambio: los accesos de la tarde-noche, que en UTC caen al
 * día siguiente, tienen que atribuirse al día local correcto.
 */
class DiaNaturalMeridaTest extends TestCase
{
    use RefreshDatabase;

    private function miembroFlex(): User
    {
        Espacio::factory()->create(['tipo' => TipoEspacio::Coworking->value, 'disponible' => true]);

        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->flex()->create();   // 4 días de coworking
        app(GestorDeMembresias::class)->alta($miembro, $plan, null, now()->subDay()->toDateString());

        return $miembro;
    }

    private function diasConsumidos(User $miembro): int
    {
        $sub = $miembro->suscripciones()->firstOrFail();

        return MovimientoHoras::where('suscripcion_id', $sub->id)
            ->where('bolsa', BolsaDeHoras::Dias->value)
            ->where('motivo', MotivoMovimiento::Acceso->value)
            ->count();
    }

    public function test_dos_accesos_del_mismo_dia_de_merida_consumen_un_solo_dia_aunque_crucen_la_medianoche_utc(): void
    {
        config(['nodico.zona_horaria' => 'America/Merida']);
        $miembro = $this->miembroFlex();
        $reg     = app(RegistroDeAcceso::class);

        // Mismo día en Mérida (2026-09-01), pero distinto día en UTC:
        //   17:00 Mérida = 23:00 UTC del 09-01
        //   19:00 Mérida = 01:00 UTC del 09-02
        $tarde = CarbonImmutable::parse('2026-09-01 17:00:00', 'America/Merida');
        $noche = CarbonImmutable::parse('2026-09-01 19:00:00', 'America/Merida');

        $reg->entrada($miembro, null, $tarde);
        $reg->salida($miembro, $tarde->addHour());
        $reg->entrada($miembro, null, $noche);

        $this->assertSame(1, $this->diasConsumidos($miembro),
            'Dos accesos del mismo día de Mérida deben consumir un solo día, aunque crucen la medianoche UTC.');
    }

    public function test_accesos_en_dias_de_merida_distintos_consumen_un_dia_cada_uno(): void
    {
        config(['nodico.zona_horaria' => 'America/Merida']);
        $miembro = $this->miembroFlex();
        $reg     = app(RegistroDeAcceso::class);

        $reg->entrada($miembro, null, CarbonImmutable::parse('2026-09-01 10:00:00', 'America/Merida'));
        $reg->salida($miembro, CarbonImmutable::parse('2026-09-01 12:00:00', 'America/Merida'));
        $reg->entrada($miembro, null, CarbonImmutable::parse('2026-09-02 10:00:00', 'America/Merida'));

        $this->assertSame(2, $this->diasConsumidos($miembro));
    }
}
