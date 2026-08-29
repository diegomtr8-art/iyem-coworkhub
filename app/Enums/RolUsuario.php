<?php

namespace App\Enums;

/**
 * Los tres roles del sistema.
 *
 * Antes eran cadenas sueltas ('admin', 'miembro') repartidas por controladores
 * y middlewares. Un `tipo` nulo, un valor nuevo o una fila creada a mano no era
 * ninguno de los dos, y el usuario rebotaba entre /dashboard y /portal hasta
 * que el navegador cortaba la cadena (A.1).
 *
 * El valor guardado en `users.tipo` se resuelve **siempre** con `tryFrom`,
 * nunca con `from` ni con un cast de Eloquent: un rol desconocido tiene que
 * poder representarse como `null` y terminar en un 403 explicado, no en una
 * excepcion de PHP ni en otra redireccion.
 */
enum RolUsuario: string
{
    case Admin   = 'admin';
    case Staff   = 'staff';
    case Miembro = 'miembro';

    /** Roles que trabajan en el panel operativo (/dashboard). */
    public function esOperativo(): bool
    {
        return $this !== self::Miembro;
    }

    /**
     * Portal al que pertenece el rol. Es lo que compara `PerteneceAlPortal`,
     * de modo que anadir un cuarto rol no obliga a tocar ninguna ruta.
     */
    public function portal(): string
    {
        return $this->esOperativo() ? 'operativo' : 'miembro';
    }

    /** Nombre de la ruta a la que llega este rol al iniciar sesion. */
    public function rutaInicio(): string
    {
        return $this->esOperativo() ? 'dashboard' : 'portal.dashboard';
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin   => 'Administración',
            self::Staff   => 'Recepción',
            self::Miembro => 'Miembro',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
