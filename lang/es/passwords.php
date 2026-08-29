<?php

return [
    /*
     * B — Enumeración de cuentas.
     *
     * `sent` y `user` dicen exactamente lo mismo a propósito: la pantalla de
     * recuperación responde igual exista o no la cuenta. `user` no debería
     * llegar nunca a pantalla —el controlador ya no lo propaga— pero si algún
     * día se cuela, no delata nada.
     */
    'sent'      => 'Si esa dirección tiene una cuenta en Nódico, ya va en camino un correo para restablecer la contraseña.',
    'user'      => 'Si esa dirección tiene una cuenta en Nódico, ya va en camino un correo para restablecer la contraseña.',
    'throttled' => 'Espera un momento antes de volver a intentarlo.',
    'token'     => 'Este enlace para restablecer la contraseña ya no es válido. Pide uno nuevo.',
    'reset'     => 'Tu contraseña quedó actualizada.',
];
