<?php

namespace Tests\Feature\ApiMovil;

use App\Enums\AccionOperativa;
use App\Enums\RolUsuario;
use App\Models\EntradaBitacora;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Reportes\GeneradorDeReportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Reportes (la cara de administración), credencial QR, pagos desde la app y
 * «Mi seguridad» en la web (docs/API-MOVIL.md §8, puntos 10, 12 y 13).
 */
class ReportesCredencialYPagosTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    // ── Reportes ────────────────────────────────────────────────────────────

    public function test_cada_cara_solo_abre_sus_rutas(): void
    {
        [$miembro] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $admin     = User::factory()->admin()->create();

        $this->api('GET', 'reportes/resumen', token: $this->tokenDe($miembro))
            ->assertStatus(403)->assertJsonPath('codigo', 'sin_permiso');

        $tokenAdmin = $this->tokenDe($admin, 'reportes');
        $this->api('GET', 'reportes/resumen', token: $tokenAdmin)->assertOk();

        foreach (['inicio', 'reservas', 'membresia', 'credencial'] as $ruta) {
            $this->api('GET', $ruta, token: $tokenAdmin)->assertStatus(403)->assertJsonPath('codigo', 'sin_permiso');
        }
    }

    public function test_quitar_el_permiso_corta_el_token_en_la_siguiente_peticion(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->tokenDe($admin, 'reportes');

        $this->api('GET', 'reportes/resumen', token: $token)->assertOk();

        $admin->asignarRol(RolUsuario::Staff)->save();

        $this->api('GET', 'reportes/resumen', token: $token)->assertStatus(403);
    }

    public function test_los_numeros_son_los_mismos_que_los_del_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $sala  = $this->sala();
        [$m, $s] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        \App\Models\Reserva::create([
            'espacio_id' => $sala->id, 'user_id' => $m->id, 'suscripcion_id' => $s->id,
            'fecha' => today()->startOfMonth()->toDateString(), 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
            'estatus' => 'Completada', 'precio_total' => 0,
        ]);

        $desde = today()->startOfMonth()->toDateString();
        $hasta = today()->toDateString();

        $api = $this->api('GET', "reportes/ocupacion?desde={$desde}&hasta={$hasta}", token: $this->tokenDe($admin, 'reportes'))
            ->assertOk()
            ->json('data.por_espacio');

        $panel = app(GeneradorDeReportes::class)->ocupacionPorEspacio(
            \Carbon\CarbonImmutable::parse($desde), \Carbon\CarbonImmutable::parse($hasta),
        );

        $this->assertEquals($panel, $api);
    }

    public function test_consultar_miembros_en_riesgo_deja_rastro(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->tokenDe($admin, 'reportes');

        $this->api('GET', 'reportes/en-riesgo', token: $token)->assertOk();
        $this->api('GET', 'reportes/en-riesgo', token: $token)->assertOk();

        $this->assertSame(1, EntradaBitacora::where('actor_user_id', $admin->id)
            ->where('accion', AccionOperativa::ConsultaReportes->value)->count(), 'Una entrada por hora, no por petición.');
    }

    // ── Credencial ──────────────────────────────────────────────────────────

    public function test_la_credencial_se_emite_y_recepcion_la_valida(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create(), ['face_id_ok' => true]);
        $staff = User::factory()->staff()->create();

        $codigo = $this->api('GET', 'credencial', token: $this->tokenDe($ana))
            ->assertOk()
            ->assertJsonPath('data.plan', 'Nodo Pro')
            ->json('data.codigo');

        $this->assertStringStartsWith('NDC1.', $codigo);

        $this->actingAs($staff)
            ->post(route('accesos.credencial.validar'), ['codigo' => $codigo])
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Accesos/Credencial')
                ->where('resultado.semaforo', 'verde')
                ->where('resultado.persona.id', $ana->id));

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion' => AccionOperativa::ValidacionCredencial->value, 'sujeto_user_id' => $ana->id,
        ]);
    }

    public function test_renovar_la_credencial_invalida_la_anterior(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $token = $this->tokenDe($ana);

        $vieja = $this->api('GET', 'credencial', token: $token)->json('data.codigo');
        $nueva = $this->api('POST', 'credencial/renovar', token: $token)->json('data.codigo');

        $this->assertNotSame($vieja, $nueva);

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('accesos.credencial.validar'), ['codigo' => $vieja])
            ->assertInertia(fn ($p) => $p->where('resultado.encontrada', false));
    }

    public function test_una_cuenta_suspendida_sale_en_rojo_aunque_el_codigo_siga_guardado(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $codigo = $this->api('GET', 'credencial', token: $this->tokenDe($ana))->json('data.codigo');

        $ana->cambiarEstado(\App\Enums\EstadoCuenta::Suspendida)->save();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('accesos.credencial.validar'), ['codigo' => $codigo])
            ->assertInertia(fn ($p) => $p->where('resultado.semaforo', 'rojo'));
    }

    public function test_un_miembro_no_puede_validar_credenciales_en_el_panel(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());

        $this->actingAs($ana)->post(route('accesos.credencial.validar'), ['codigo' => 'NDC1.x'])->assertForbidden();
    }

    // ── Pagos ───────────────────────────────────────────────────────────────

    public function test_referencia_con_factura_sin_datos_fiscales_se_rechaza(): void
    {
        $plan  = Plane::factory()->nodoPro()->create(['activo' => true]);
        $ana   = User::factory()->miembro()->create();

        $this->apiIdempotente('pagos/referencia', ['plan_id' => $plan->id, 'metodo' => 'transferencia', 'pide_factura' => true], $this->tokenDe($ana))
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'datos_fiscales_incompletos');

        $this->assertSame(0, OrdenPago::count());
    }

    public function test_la_misma_clave_no_crea_dos_referencias_y_nada_se_activa(): void
    {
        Notification::fake();
        $plan  = Plane::factory()->nodoPro()->create(['activo' => true]);
        $ana   = User::factory()->miembro()->create();
        $token = $this->tokenDe($ana);
        $datos = ['plan_id' => $plan->id, 'metodo' => 'efectivo', 'pide_factura' => false];

        $this->apiIdempotente('pagos/referencia', $datos, $token, 'referencia-1234')->assertCreated();
        $this->apiIdempotente('pagos/referencia', $datos, $token, 'referencia-1234')->assertCreated();

        $this->assertSame(1, OrdenPago::count());
        $this->assertSame(0, $ana->suscripciones()->count(), 'Generar la referencia no activa la membresía.');
    }

    /**
     * Regresión del cobro doble: con una suscripción recurrente ya activa, ni
     * se prepara otra tarjeta ni se crea una segunda suscripción. Se corta
     * antes de hablar con Stripe.
     */
    public function test_no_se_crea_una_segunda_suscripcion_recurrente(): void
    {
        config(['cashier.key' => 'pk_test_x', 'cashier.secret' => 'sk_test_x']);
        $plan = Plane::factory()->nodoPro()->create(['activo' => true, 'cobro_recurrente' => true, 'stripe_price_id' => 'price_x']);
        $ana  = User::factory()->miembro()->create(['stripe_id' => 'cus_x']);

        $ana->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_x', 'stripe_status' => 'active', 'stripe_price' => 'price_x', 'quantity' => 1,
        ]);

        $token = $this->tokenDe($ana);

        $this->apiIdempotente('pagos/tarjeta/suscribir', ['plan_id' => $plan->id, 'setup_intent_id' => 'seti_x'], $token)
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');

        $this->assertSame(1, $ana->subscriptions()->count());
    }

    public function test_sin_claves_de_stripe_la_tarjeta_no_esta_disponible(): void
    {
        config(['cashier.key' => null, 'cashier.secret' => null]);
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $ana  = User::factory()->miembro()->create();

        $this->api('GET', 'estado')->assertJsonPath('data.pagos.tarjeta', false);

        $this->apiIdempotente('pagos/tarjeta', ['plan_id' => $plan->id], $this->tokenDe($ana))
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    // ── Borrar la cuenta ────────────────────────────────────────────────────

    /**
     * Regresión: con órdenes de pago, borrar la cuenta daba 500 (la llave
     * foránea lo impedía). Ahora se borra, las órdenes pagadas se conservan sin
     * dueño (registro fiscal) y las pendientes se cancelan.
     */
    public function test_borrar_la_cuenta_con_ordenes_de_pago_funciona_y_conserva_lo_pagado(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        [$ana] = $this->miembroCon($plan);

        $base = [
            'user_id' => $ana->id, 'plan_id' => $plan->id, 'monto' => 599, 'metodo' => \App\Enums\MetodoReferencia::Efectivo,
            'pide_factura' => false, 'estado_factura' => \App\Enums\EstadoFacturaOrden::NoSolicitada, 'vence_el' => now()->addDays(7),
        ];
        $pagada = OrdenPago::create([...$base, 'referencia' => 'NOD-PAGADA', 'referencia_normalizada' => 'NODPAGADA',
            'estado_pago' => \App\Enums\EstadoPagoOrden::Confirmada]);
        $pendiente = OrdenPago::create([...$base, 'referencia' => 'NOD-PENDIENTE', 'referencia_normalizada' => 'NODPENDIENTE',
            'estado_pago' => \App\Enums\EstadoPagoOrden::Generada]);

        $this->api('DELETE', 'yo', ['password' => 'password'], $this->tokenDe($ana))->assertOk();

        $this->assertModelMissing($ana);
        $this->assertNull($pagada->refresh()->user_id, 'La orden pagada se conserva, sin dueño.');
        $this->assertSame(\App\Enums\EstadoPagoOrden::Cancelada, $pendiente->refresh()->estado_pago);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_borrar_la_cuenta_exige_la_contrasena_correcta(): void
    {
        $ana = User::factory()->miembro()->create();

        $this->api('DELETE', 'yo', ['password' => 'mala'], $this->tokenDe($ana))->assertStatus(422);

        $this->assertModelExists($ana);
    }

    // ── «Mi seguridad» en la web ────────────────────────────────────────────

    public function test_desde_la_web_se_cierra_la_sesion_de_un_telefono(): void
    {
        $ana   = User::factory()->miembro()->create();
        $token = $this->tokenDe($ana);
        $id    = $ana->tokens()->first()->id;

        $this->actingAs($ana)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.cerrar-telefono', ['token' => $id]))
            ->assertRedirect();

        $this->assertSame(0, $ana->tokens()->count());
        $this->api('GET', 'yo', token: $token)->assertStatus(401);
    }

    public function test_desde_la_web_no_se_cierra_el_telefono_de_otro(): void
    {
        $ana  = User::factory()->miembro()->create();
        $beto = User::factory()->miembro()->create();
        $this->tokenDe($beto);

        $this->actingAs($ana)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.cerrar-telefono', ['token' => $beto->tokens()->first()->id]))
            ->assertNotFound();

        $this->assertSame(1, $beto->tokens()->count());
    }
}
