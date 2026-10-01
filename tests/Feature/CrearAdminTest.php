<?php

namespace Tests\Feature;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El primer administrador de producción se crea con un comando, sin teclear
 * contraseñas en la terminal ni usar el administrador de desarrollo: la
 * persona recibe el enlace para poner la suya (docs/PRODUCCION.md).
 */
class CrearAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_admin_activo_y_le_manda_el_enlace_para_su_contrasena(): void
    {
        Notification::fake();

        $this->artisan('nodico:crear-admin', ['correo' => 'Direccion@IYEM.gob.mx', 'nombre' => 'Dirección Nódico'])
            ->assertSuccessful();

        $admin = User::where('email', 'direccion@iyem.gob.mx')->sole();
        $this->assertSame(RolUsuario::Admin, $admin->rol);
        $this->assertSame(EstadoCuenta::Activa, $admin->estado);
        $this->assertTrue($admin->hasVerifiedEmail());
        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_no_toca_una_cuenta_que_ya_existe(): void
    {
        Notification::fake();
        $miembro = User::factory()->miembro()->create(['email' => 'ya@example.com']);

        $this->artisan('nodico:crear-admin', ['correo' => 'ya@example.com', 'nombre' => 'X'])->assertFailed();

        $this->assertSame(RolUsuario::Miembro, $miembro->fresh()->rol, 'No se asciende a nadie por accidente.');
        Notification::assertNothingSent();
    }

    public function test_rechaza_un_correo_invalido(): void
    {
        $this->artisan('nodico:crear-admin', ['correo' => 'no-es-correo', 'nombre' => 'X'])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
