<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DatabaseSeeder es de desarrollo: crea un administrador con una contraseña
 * escrita en el repositorio y datos inventados. Un `db:seed --force` en
 * producción dejaría una puerta abierta. Lista blanca, como DemoSeeder.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_niega_fuera_de_local_staging_y_pruebas(): void
    {
        foreach (['production', 'prod', 'produccion'] as $entorno) {
            $this->app['env'] = $entorno;

            try {
                (new DatabaseSeeder())->setContainer($this->app)->run();
                $this->fail("Corrió con APP_ENV='{$entorno}'.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('no corre', $e->getMessage());
            }

            $this->assertSame(0, User::where('email', 'admin@nodico.com.mx')->count());
        }
    }
}
