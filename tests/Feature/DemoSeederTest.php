<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.F — el seeder de demostración crea datos de prueba, así que sobre una
 * base real sería un desastre. El blindaje contra `production` es obligatorio.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_niega_a_correr_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);

        try {
            (new DemoSeeder())->setContainer($this->app)->run();
        } finally {
            // No debe haber tocado nada: ni un usuario de demo creado.
            $this->assertSame(0, User::where('email', 'like', '%.demo@%')->count());
        }
    }
}
