<?php

/*
 * Mensajes de validación en español.
 *
 * Hasta ahora `APP_LOCALE` era `en` y no existía carpeta `lang/`, así que todo
 * error de formulario salía en inglés —«These credentials do not match our
 * records»— en un sitio que por lo demás está entero en español. Se cubren las
 * reglas que este proyecto usa de verdad; una regla sin traducir se nota
 * enseguida porque sale como «validation.loquesea».
 */

return [
    'accepted'         => 'Tienes que aceptar :attribute para continuar.',
    'active_url'       => ':attribute no es una dirección válida.',
    'after'            => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal'   => ':attribute debe ser una fecha igual o posterior a :date.',
    'alpha'            => ':attribute solo puede contener letras.',
    'alpha_dash'       => ':attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num'        => ':attribute solo puede contener letras y números.',
    'array'            => ':attribute debe ser una lista.',
    'before'           => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal'  => ':attribute debe ser una fecha igual o anterior a :date.',
    'boolean'          => ':attribute solo puede ser verdadero o falso.',
    'confirmed'        => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña no es correcta.',
    'date'             => ':attribute no es una fecha válida.',
    'date_equals'      => ':attribute debe ser una fecha igual a :date.',
    'date_format'      => ':attribute no corresponde al formato :format.',
    'different'        => ':attribute y :other deben ser distintos.',
    'digits'           => ':attribute debe tener :digits dígitos.',
    'digits_between'   => ':attribute debe tener entre :min y :max dígitos.',
    'email'            => ':attribute debe ser una dirección de correo válida.',
    'ends_with'        => ':attribute debe terminar en alguno de estos valores: :values.',
    'exists'           => ':attribute seleccionado no existe.',
    'file'             => ':attribute debe ser un archivo.',
    'filled'           => ':attribute no puede quedar vacío.',
    'image'            => ':attribute debe ser una imagen.',
    'in'               => ':attribute seleccionado no es válido.',
    'integer'          => ':attribute debe ser un número entero.',
    'ip'               => ':attribute debe ser una dirección IP válida.',
    'json'             => ':attribute debe ser una cadena JSON válida.',
    'lowercase'        => ':attribute debe ir en minúsculas.',
    'max'              => [
        'numeric' => ':attribute no puede ser mayor que :max.',
        'file'    => ':attribute no puede pesar más de :max kilobytes.',
        'string'  => ':attribute no puede tener más de :max caracteres.',
        'array'   => ':attribute no puede tener más de :max elementos.',
    ],
    'mimes'     => ':attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':attribute debe ser un archivo de tipo: :values.',
    'min'       => [
        'numeric' => ':attribute debe ser al menos :min.',
        'file'    => ':attribute debe pesar al menos :min kilobytes.',
        'string'  => ':attribute debe tener al menos :min caracteres.',
        'array'   => ':attribute debe tener al menos :min elementos.',
    ],
    'not_in'    => ':attribute seleccionado no es válido.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric'   => ':attribute debe ser un número.',
    'present'   => ':attribute debe estar presente.',
    'regex'     => 'El formato de :attribute no es válido.',
    'required'  => ':attribute es obligatorio.',
    'required_if'     => ':attribute es obligatorio cuando :other es :value.',
    'required_unless' => ':attribute es obligatorio salvo que :other esté en :values.',
    'required_with'   => ':attribute es obligatorio cuando :values está presente.',
    'required_without' => ':attribute es obligatorio cuando :values no está presente.',
    'same'      => ':attribute y :other deben coincidir.',
    'size'      => [
        'numeric' => ':attribute debe ser :size.',
        'file'    => ':attribute debe pesar :size kilobytes.',
        'string'  => ':attribute debe tener :size caracteres.',
        'array'   => ':attribute debe contener :size elementos.',
    ],
    'starts_with' => ':attribute debe empezar por alguno de estos valores: :values.',
    'string'      => ':attribute debe ser texto.',
    'timezone'    => ':attribute debe ser una zona horaria válida.',
    'unique'      => ':attribute ya está en uso.',
    'uploaded'    => 'No se pudo subir :attribute.',
    'uppercase'   => ':attribute debe ir en mayúsculas.',
    'url'         => ':attribute debe ser una dirección web válida.',

    /*
     * Reglas de contraseña. El mínimo son 10 caracteres con letras y números,
     * sin exigir símbolos ni caducidad: el NIST desaconseja ambos desde 2017
     * porque empujan a la gente hacia contraseñas peores y más reutilizadas.
     */
    'password' => [
        'letters'       => 'La contraseña debe incluir al menos una letra.',
        'mixed'         => 'La contraseña debe incluir mayúsculas y minúsculas.',
        'numbers'       => 'La contraseña debe incluir al menos un número.',
        'symbols'       => 'La contraseña debe incluir al menos un símbolo.',
        'uncompromised' => 'Esa contraseña aparece en filtraciones públicas de datos. Aunque sea tuya y nadie la sepa, ya está en las listas que se usan para entrar a cuentas ajenas: elige otra.',
    ],

    'custom' => [
        'acepta_legales' => [
            'accepted' => 'Para crear tu cuenta necesitas aceptar el aviso de privacidad y los términos.',
        ],
    ],

    /*
     * Nombres legibles. Sin esto los mensajes salen como «El campo password_confirmation…».
     */
    'attributes' => [
        'name'                  => 'el nombre',
        'email'                 => 'el correo',
        'password'              => 'la contraseña',
        'password_confirmation' => 'la confirmación de la contraseña',
        'current_password'      => 'la contraseña actual',
        'telefono'              => 'el teléfono',
        'empresa'               => 'la empresa o proyecto',
        'ocupacion'             => 'la ocupación',
        'codigo'                => 'el código',
        'mensaje'               => 'el mensaje',
        'asunto'                => 'el asunto',
        'nombre'                => 'el nombre',
        'acepta_legales'        => 'el aviso de privacidad y los términos',
    ],
];
