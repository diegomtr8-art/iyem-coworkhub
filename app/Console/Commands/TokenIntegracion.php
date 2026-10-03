<?php

namespace App\Console\Commands;

use App\Enums\EstadoCuenta;
use App\Enums\EventoAuth;
use App\Enums\RolUsuario;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Servicios\Acceso\EmisorDeTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cuenta de servicio del IYEM ERP y su token de reportes (docs/API-MOVIL.md §6.11).
 *
 * El ERP del instituto pinta los reportes de Nódico en su propio tablero. La
 * cara `reportes` exige un usuario operativo con `ver-reportes`, y el ERP no
 * puede colgarse de la cuenta de una persona: si esa persona se va, la
 * integración se cae con ella. Por eso hay una identidad de máquina.
 *
 * - **Sin contraseña utilizable.** Se crea con una aleatoria que nadie conoce y
 *   no se usa para entrar al panel.
 * - **Un solo token vivo.** Volver a correr el comando emite uno nuevo y
 *   revoca el anterior (mismo `dispositivo_id`, lo hace `EmisorDeTokens`).
 * - **Se imprime una sola vez.** Sanctum guarda el hash; si se pierde, se
 *   emite otro.
 * - **Se corta al instante** con `--revocar`, o quitándole el rol de
 *   administración desde el panel: `CaraDelToken` revisa el permiso en cada
 *   petición.
 */
class TokenIntegracion extends Command
{
    /** Marca en `notas_admin` que distingue a la cuenta de servicio de una persona. */
    public const MARCA = 'Cuenta de servicio del IYEM ERP. No es de una persona: no se usa para entrar al panel.';

    public const DISPOSITIVO_ID = 'iyem-erp';

    protected $signature = 'nodico:token-integracion
        {--correo=erp@iyemyucatan.com : Correo de la cuenta de servicio}
        {--nombre=IYEM ERP (integración) : Nombre con que aparece en el panel y en la bitácora}
        {--revocar : Revoca el token vigente y no emite otro}';

    protected $description = 'Crea la cuenta de servicio del IYEM ERP y emite (o revoca) su token de reportes.';

    public function handle(EmisorDeTokens $emisor): int
    {
        $correo = Str::lower(trim((string) $this->option('correo')));
        $cuenta = User::where('email', $correo)->first();

        // Una cuenta con ese correo que no lleva la marca es de alguien: no se
        // asciende a administración por un error de dedo.
        if ($cuenta && ! str_contains((string) $cuenta->notas_admin, self::MARCA)) {
            $this->error("Ya hay una cuenta con {$correo} y no es la de servicio. No se toca.");

            return self::FAILURE;
        }

        if ($this->option('revocar')) {
            return $this->revocar($cuenta, $correo);
        }

        $cuenta ??= $this->crearCuenta($correo, trim((string) $this->option('nombre')));

        $datos = $emisor->emitir($cuenta, $emisor->caraDe($cuenta), [
            'dispositivo'    => 'IYEM ERP',
            'dispositivo_id' => self::DISPOSITIVO_ID,
            'plataforma'     => null,
        ], 'integracion-erp');

        // El token no va al registro: solo quién, cuándo y que hubo emisión.
        Log::notice('Token de integración del IYEM ERP emitido.', ['user_id' => $cuenta->id, 'correo' => $correo]);

        $this->info("Token de reportes para {$correo}. Se muestra una sola vez:");
        $this->newLine();
        $this->line($datos['token']);
        $this->newLine();
        $this->warn('Cópialo a NODICO_TOKEN en el .env del ERP. No lo pegues en un commit, un chat ni un correo.');
        $this->line("Caduca tras {$datos['caduca_por_inactividad_en_dias']} días sin uso; el token anterior, si había, ya no sirve.");

        return self::SUCCESS;
    }

    private function crearCuenta(string $correo, string $nombre): User
    {
        $cuenta = User::create([
            'name'        => $nombre,
            'email'       => $correo,
            'password'    => Str::password(64),
            'notas_admin' => self::MARCA,
        ]);

        // Hoy `ver-reportes` solo lo trae el rol de administración (AuthServiceProvider).
        $cuenta->asignarRol(RolUsuario::Admin);
        $cuenta->cambiarEstado(EstadoCuenta::Activa);
        $cuenta->marcarCorreoVerificado();

        $this->info("Cuenta de servicio creada: {$correo}.");

        return $cuenta;
    }

    private function revocar(?User $cuenta, string $correo): int
    {
        if (! $cuenta) {
            $this->warn("No existe la cuenta de servicio {$correo}: no hay nada que revocar.");

            return self::SUCCESS;
        }

        $borrados = $cuenta->tokens()->delete();

        EventoAutenticacion::registrar(EventoAuth::CierreRemoto, $cuenta, contexto: ['via' => 'integracion-erp', 'tokens' => $borrados]);
        Log::notice('Token de integración del IYEM ERP revocado.', ['user_id' => $cuenta->id, 'tokens' => $borrados]);

        $this->info("Tokens revocados: {$borrados}. El ERP deja de ver los reportes en su siguiente consulta.");

        return self::SUCCESS;
    }
}
