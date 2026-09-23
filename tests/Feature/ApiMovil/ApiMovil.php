<?php

namespace Tests\Feature\ApiMovil;

use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas de la API móvil: sacar un token como lo haría la
 * app y llamar con las cabeceras de la app.
 */
trait ApiMovil
{
    protected function tokenDe(User $usuario, string $cara = 'miembro'): string
    {
        return $usuario->createToken('Teléfono de prueba', [$cara])->plainTextToken;
    }

    protected function api(string $metodo, string $ruta, array $datos = [], ?string $token = null, array $cabeceras = []): TestResponse
    {
        // El guard recuerda al usuario de la petición anterior dentro de la
        // misma prueba; en la vida real cada petición llega sola.
        $this->app['auth']->forgetGuards();

        return $this->json($metodo, '/api/v1/' . ltrim($ruta, '/'), $datos, [
            'X-App-Version'    => '1.0.0',
            'X-App-Plataforma' => 'ios',
            ...($token ? ['Authorization' => 'Bearer ' . $token] : []),
            ...$cabeceras,
        ]);
    }

    /** Las escrituras que exigen `Idempotency-Key`. */
    protected function apiIdempotente(string $ruta, array $datos, string $token, ?string $clave = null): TestResponse
    {
        return $this->api('POST', $ruta, $datos, $token, ['Idempotency-Key' => $clave ?? (string) Str::uuid()]);
    }

    /** @return array{0: User, 1: Suscripcion} */
    protected function miembroCon(Plane $plan, array $usuario = []): array
    {
        $user = User::factory()->miembro()->create($usuario);

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonth(),
        ]);

        return [$user, $suscripcion];
    }

    /** Próximo lunes: hábil y dentro de horario. */
    protected function diaHabil(int $sumarDias = 0): string
    {
        return today()->next(Carbon::MONDAY)->addDays($sumarDias)->toDateString();
    }

    protected function sala(): Espacio
    {
        return Espacio::factory()->salaJuntas()->create();
    }

    /** @return array<string, string> */
    protected function dispositivo(string $id = 'telefono-de-prueba-1'): array
    {
        return ['dispositivo' => 'iPhone de prueba', 'dispositivo_id' => $id, 'plataforma' => 'ios'];
    }
}
