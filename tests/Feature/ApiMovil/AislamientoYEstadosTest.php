<?php

namespace Tests\Feature\ApiMovil;

use App\Enums\EstadoAsesoria;
use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un miembro no puede leer ni tocar nada de otro **por la API** — contra el
 * JSON, no contra la pantalla. Y los tres frenos de la cuenta (suspendida,
 * correo sin verificar, consentimiento) llegan con su `codigo`.
 */
class AislamientoYEstadosTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    public function test_un_miembro_no_ve_ni_toca_nada_de_otro(): void
    {
        Storage::fake('local');
        $plan = Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1]);

        [$ana]            = $this->miembroCon($plan, ['email' => 'ana@correo.mx']);
        [$beto, $susBeto] = $this->miembroCon($plan, ['email' => 'beto@secreto.mx', 'name' => 'Beto Secreto']);

        $reservaBeto = Reserva::create([
            'espacio_id' => $this->sala()->id, 'user_id' => $beto->id, 'suscripcion_id' => $susBeto->id,
            'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
            'estatus' => 'Confirmada', 'precio_total' => 0,
        ]);

        $asesoriaBeto = SolicitudAsesoria::create([
            'user_id' => $beto->id, 'suscripcion_id' => $susBeto->id, 'tema' => 'Finanzas',
            'dia_preferido' => $this->diaHabil(), 'horario_preferido' => 'Por la mañana', 'horas' => 1,
            'estado' => EstadoAsesoria::Solicitada,
        ]);

        Storage::disk('local')->put('facturas/beto.pdf', 'PDF');
        $ordenBeto = OrdenPago::create([
            'referencia' => 'NOD-BETO-1', 'referencia_normalizada' => 'NODBETO1', 'user_id' => $beto->id,
            'plan_id' => $plan->id, 'monto' => 599, 'metodo' => MetodoReferencia::Transferencia,
            'pide_factura' => true, 'estado_pago' => EstadoPagoOrden::Generada,
            'estado_factura' => EstadoFacturaOrden::Emitida, 'factura_pdf' => 'facturas/beto.pdf',
            'factura_emitida_en' => now(), 'vence_el' => now()->addDays(7),
        ]);

        $token = $this->tokenDe($ana);

        // Recurso por recurso: 404, indistinguible de «no existe».
        $this->api('GET', "reservas/{$reservaBeto->id}", token: $token)->assertNotFound();
        $this->api('POST', "reservas/{$reservaBeto->id}/cancelar", token: $token)->assertNotFound();
        $this->api('POST', "asesorias/{$asesoriaBeto->id}/cancelar", token: $token)->assertNotFound();
        $this->api('GET', "pagos/{$ordenBeto->id}", token: $token)->assertNotFound();
        $this->api('POST', "pagos/{$ordenBeto->id}/ya-pague", token: $token)->assertNotFound();
        $this->api('GET', "facturas/{$ordenBeto->id}/pdf", token: $token)->assertNotFound();

        // Beto intacto.
        $this->assertSame('Confirmada', $reservaBeto->refresh()->estatus);
        $this->assertSame(EstadoAsesoria::Solicitada, $asesoriaBeto->refresh()->estado);
        $this->assertNull($ordenBeto->refresh()->reportado_pagado_en);

        // Y ninguna lista de Ana trae nada de Beto, buscado en el cuerpo crudo.
        foreach (['inicio', 'reservas', 'reservas?tipo=pasadas', 'asesorias', 'pagos', 'facturas', 'cobros', 'membresia', 'avisos', 'yo', 'credencial'] as $ruta) {
            $cuerpo = $this->api('GET', $ruta, token: $token)->assertOk()->getContent();

            $this->assertStringNotContainsString('beto@secreto.mx', $cuerpo, "«{$ruta}» filtra el correo de otro miembro.");
            $this->assertStringNotContainsString('Beto Secreto', $cuerpo, "«{$ruta}» filtra el nombre de otro miembro.");
            $this->assertStringNotContainsString('NOD-BETO-1', $cuerpo, "«{$ruta}» filtra una orden de otro miembro.");
        }
    }

    public function test_sin_token_todo_es_401(): void
    {
        foreach (['yo', 'inicio', 'reservas', 'membresia', 'credencial', 'reportes/resumen'] as $ruta) {
            $this->api('GET', $ruta)->assertStatus(401)->assertJsonPath('codigo', 'no_autenticado');
        }
    }

    public function test_una_cuenta_suspendida_recibe_su_codigo_en_todo(): void
    {
        $token = $this->tokenDe(User::factory()->miembro()->suspendida()->create());

        foreach (['yo', 'inicio', 'reservas', 'membresia', 'credencial', 'yo/consentimiento'] as $ruta) {
            $this->api('GET', $ruta, token: $token)->assertStatus(403)->assertJsonPath('codigo', 'cuenta_suspendida');
        }
    }

    public function test_correo_sin_verificar_recibe_su_codigo(): void
    {
        $token = $this->tokenDe(User::factory()->miembro()->unverified()->create());

        $this->api('GET', 'inicio', token: $token)->assertStatus(403)->assertJsonPath('codigo', 'correo_sin_verificar');

        // Pero puede saber quién es y pedir otro correo.
        $this->api('GET', 'yo', token: $token)->assertOk()->assertJsonPath('data.correo_verificado', false);
    }

    public function test_consentimiento_pendiente_bloquea_salvo_las_rutas_para_aceptarlo(): void
    {
        $token = $this->tokenDe(User::factory()->miembro()->sinConsentimiento()->create());

        $this->api('GET', 'inicio', token: $token)
            ->assertStatus(403)
            ->assertJsonPath('codigo', 'consentimiento_pendiente');

        $this->api('GET', 'yo/consentimiento', token: $token)->assertOk()->assertJsonCount(2, 'data.pendientes');
        $this->api('POST', 'yo/consentimiento', ['acepta' => true], $token)->assertOk();

        $this->api('GET', 'inicio', token: $token)->assertOk();
    }

    public function test_revocar_otro_telefono_lo_desconecta(): void
    {
        $miembro = User::factory()->miembro()->create();
        $este    = $this->tokenDe($miembro);
        $perdido = $this->tokenDe($miembro);

        $idPerdido = collect($this->api('GET', 'yo/dispositivos', token: $este)->json('data'))
            ->firstWhere('es_este', false)['id'];

        $this->api('DELETE', "yo/dispositivos/{$idPerdido}", token: $este)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->api('GET', 'yo', token: $perdido)->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->api('GET', 'yo', token: $este)->assertOk();
    }

    public function test_no_se_revoca_el_telefono_de_otra_persona(): void
    {
        $ana  = User::factory()->miembro()->create();
        $beto = User::factory()->miembro()->create();
        $this->tokenDe($beto);

        $this->api('DELETE', 'yo/dispositivos/' . $beto->tokens()->first()->id, token: $this->tokenDe($ana))->assertNotFound();

        $this->assertSame(1, $beto->tokens()->count());
    }
}
