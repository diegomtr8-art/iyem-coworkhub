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
        // Fase 3: recepcion atiende asesorias y cotiza salones. Son operacion
        // diaria de mostrador, no configuracion del negocio.
        'gestionar-asesorias',
        'gestionar-salones',
        // El prompt lo pide explicito: recepcion repone o descuenta horas.
        // Siempre con motivo obligatorio y siempre como movimiento del libro,
        // que es lo que hace seguro darle este permiso.
        'ajustar-horas',
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
        // Fase 3: el catalogo de asesores y los dias festivos son
        // configuracion, no operacion. Y ajustar horas mueve saldo.
        'gestionar-catalogos',
    ];

    /** Caja: cobra y confirma pagos en el mostrador; nada mas. */
    public const CAJA = [
        'operar-caja',
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
        return array_merge(self::OPERACION, self::ADMINISTRACION, self::CAJA);
    }

    /**
     * Permisos que tiene cada rol. Un solo lugar donde vive «quién puede qué»:
     * administracion todo; recepcion la operacion diaria; caja solo su mostrador.
     *
     * @return array<int, string>
     */
    public static function permisosDeRol(?RolUsuario $rol): array
    {
        return match ($rol) {
            RolUsuario::Admin => self::todos(),
            RolUsuario::Staff => self::OPERACION,
            RolUsuario::Caja  => self::CAJA,
            default           => [],
        };
    }

    public function boot(): void
    {
        foreach (self::todos() as $permiso) {
            Gate::define($permiso, fn (User $usuario) => in_array(
                $permiso, self::permisosDeRol($usuario->rol), true,
            ));
        }
    }
}
