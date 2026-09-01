<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secreto compartido con el agente de acceso
    |--------------------------------------------------------------------------
    | El mismo valor que el agente local guarda como `NODICO_SECRETO`. Con él se
    | firma (HMAC-SHA256) cada envío del agente y con él se verifica aquí.
    |
    | **Si está vacío, el endpoint rechaza todo**: sin secreto no se procesa nada
    | sin firmar. No se pone valor por defecto a propósito —un secreto por defecto
    | es no tener secreto.
    */
    'agente_secreto' => env('ACCESO_AGENTE_SECRETO'),

    /*
    |--------------------------------------------------------------------------
    | Antirrebote de reconocimientos
    |--------------------------------------------------------------------------
    | El terminal puede emitir varios eventos de la misma cara en pocos segundos
    | (la persona parada frente al lector). Dentro de esta ventana, un segundo
    | reconocimiento del mismo miembro en la misma dirección **se guarda como
    | evento** pero **no genera un check-in nuevo**. Independiente del consumo de
    | día, que ocurre una sola vez por día natural.
    */
    'antirrebote_segundos' => (int) env('ACCESO_ANTIRREBOTE_SEGUNDOS', 90),

    /*
    |--------------------------------------------------------------------------
    | Ventana anti-replay de la firma
    |--------------------------------------------------------------------------
    | El `X-Agente-Timestamp` de cada petición debe caer dentro de esta ventana
    | respecto a la hora del servidor. Fuera de ella se rechaza, aunque la firma
    | cuadre: así una petición capturada no se puede reenviar horas después.
    */
    'replay_ventana_segundos' => (int) env('ACCESO_REPLAY_VENTANA_SEGUNDOS', 300),

];
