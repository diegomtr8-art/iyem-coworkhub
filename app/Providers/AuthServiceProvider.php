<?php

namespace App\Providers;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * A.3 — Los permisos del panel operativo viven aqui, en un solo sitio, no
 * repartidos en `if ($user->tipo === 'admin')` por los controladores.
 *
 * Recepcion (`staff`) hace la operacion del dia: registra entradas y salidas,
 * mueve reservas, consulta el directorio de miembros y publica avisos. Todo lo
 * que toca dinero o configuracion del negocio —planes, precios, espacios,
 * facturas, eventos publicos y reportes— es exclusivo de administracion.
 */
class AuthServiceProvider extends ServiceProvider
{
    /** Operacion diaria: administracion y recepcion. */
    public const OPERACION = [
        'operar-checkins',
        'gestionar-reservas',
        'ver-miembros',
        'gestionar-anuncios',
    ];

    /** Configuracion del negocio y dinero: solo administracion. */
    public const ADMINISTRACION = [
        'gestionar-planes',
        'gestionar-espacios',
        'gestionar-facturacion',
        'gestionar-eventos',
        'editar-miembros',
        'ver-reportes',
        'ver-bitacora',
    ];

    /**
     * Todos los permisos declarados.
     *
     * Lo consume `HandleInertiaRequests` para decirle al front que puede
     * ensenar. **No es control de acceso**: el servidor autoriza cada ruta por
     * su cuenta; esto solo evita ensenar botones que van a dar 403.
     *
     * @return array<int, string>
     */
    public static function todos(): array
    {
        return array_merge(self::OPERACION, self::ADMINISTRACION);
    }

    public function boot(): void
    {
        foreach (self::OPERACION as $permiso) {
            Gate::define($permiso, fn (User $usuario) => $usuario->esOperativo());
        }

        foreach (self::ADMINISTRACION as $permiso) {
            Gate::define($permiso, fn (User $usuario) => $usuario->rol === RolUsuario::Admin);
        }
    }
}
