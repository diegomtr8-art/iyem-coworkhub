<?php

return [
    /*
     * B — Enumeración de cuentas.
     *
     * `failed` es deliberadamente vago: no dice si el correo existe ni si la
     * contraseña era la equivocada. Cualquier variante («ese correo no está
     * registrado», «contraseña incorrecta») convierte el formulario de acceso
     * en un buscador de miembros de Nódico.
     */
    'failed'   => 'Esos datos no coinciden con ninguna cuenta.',
    'password' => 'La contraseña no es correcta.',

    // Respaldo. El límite propio de Nódico compone su propio texto con la
    // cuenta regresiva; ver App\Support\ControlDeIntentos.
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',
];
