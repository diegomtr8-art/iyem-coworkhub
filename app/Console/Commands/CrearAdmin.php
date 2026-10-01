<?php

namespace App\Console\Commands;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crea el primer administrador de producción (docs/PRODUCCION.md).
 *
 * Sin contraseña en la terminal: se crea con una aleatoria que nadie conoce y
 * la persona recibe por correo el enlace para poner la suya. De paso es la
 * primera prueba de que el correo de producción funciona. Nunca se usa
 * DatabaseSeeder, que trae un administrador con contraseña en el repositorio.
 *
 * No toca una cuenta existente: ascender a alguien por un error de dedo sería
 * peor que tener que hacerlo a mano desde el panel.
 */
class CrearAdmin extends Command
{
    protected $signature = 'nodico:crear-admin {correo} {nombre}';

    protected $description = 'Crea un administrador y le manda el enlace para poner su contraseña.';

    public function handle(): int
    {
        $correo = Str::lower(trim((string) $this->argument('correo')));
        $nombre = trim((string) $this->argument('nombre'));

        if (Validator::make(['correo' => $correo], ['correo' => 'required|email:rfc'])->fails() || $nombre === '') {
            $this->error('Hace falta un correo válido y un nombre.');

            return self::FAILURE;
        }

        if (User::where('email', $correo)->exists()) {
            $this->error("Ya hay una cuenta con {$correo}. No se toca: cambia su rol desde el panel si corresponde.");

            return self::FAILURE;
        }

        $admin = User::create([
            'name'     => $nombre,
            'email'    => $correo,
            'password' => Str::password(40),
        ]);
        $admin->asignarRol(RolUsuario::Admin);
        $admin->cambiarEstado(EstadoCuenta::Activa);
        $admin->marcarCorreoVerificado();

        $estado = Password::broker()->sendResetLink(['email' => $correo]);

        if ($estado !== Password::RESET_LINK_SENT) {
            $this->warn("Cuenta creada, pero el correo no salió ({$estado}). Revisa MAIL_* y usa «Olvidé mi contraseña».");

            return self::SUCCESS;
        }

        $this->info("Administrador creado: {$correo}. Le llegó el enlace para poner su contraseña.");

        return self::SUCCESS;
    }
}
