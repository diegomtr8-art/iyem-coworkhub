<?php

/*
|--------------------------------------------------------------------------
| Catálogos del SAT
|--------------------------------------------------------------------------
| Régimen fiscal y uso de CFDI **no son campos de texto libre**: son catálogos
| cerrados del SAT y contabilidad del IYEM los necesita con su clave exacta.
| Un «régimen: simplificado» escrito a mano obliga a alguien a adivinar si eso
| era 626 o 621.
|
| `personas` dice a qué tipo de contribuyente aplica cada clave, y con eso se
| valida que un RFC de 12 posiciones (persona moral) no pida un régimen que
| solo existe para personas físicas.
|
| Nódico **no timbra**: estos datos se recopilan, se resguardan y se le pasan a
| contabilidad del IYEM, que es quien emite. Ver `docs/DEPLOY.md` y la pantalla
| de datos fiscales del portal.
|
| Vigencia de los catálogos: CFDI 4.0.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | c_RegimenFiscal
    |--------------------------------------------------------------------------
    */
    'regimenes' => [
        '601' => ['nombre' => 'General de Ley Personas Morales',                                  'personas' => ['moral']],
        '603' => ['nombre' => 'Personas Morales con Fines no Lucrativos',                         'personas' => ['moral']],
        '605' => ['nombre' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',              'personas' => ['fisica']],
        '606' => ['nombre' => 'Arrendamiento',                                                    'personas' => ['fisica']],
        '607' => ['nombre' => 'Régimen de Enajenación o Adquisición de Bienes',                   'personas' => ['fisica']],
        '608' => ['nombre' => 'Demás ingresos',                                                   'personas' => ['fisica']],
        '610' => ['nombre' => 'Residentes en el Extranjero sin Establecimiento Permanente',       'personas' => ['fisica', 'moral']],
        '611' => ['nombre' => 'Ingresos por Dividendos (socios y accionistas)',                   'personas' => ['fisica']],
        '612' => ['nombre' => 'Personas Físicas con Actividades Empresariales y Profesionales',   'personas' => ['fisica']],
        '614' => ['nombre' => 'Ingresos por intereses',                                           'personas' => ['fisica']],
        '615' => ['nombre' => 'Régimen de los ingresos por obtención de premios',                 'personas' => ['fisica']],
        '616' => ['nombre' => 'Sin obligaciones fiscales',                                        'personas' => ['fisica']],
        '620' => ['nombre' => 'Sociedades Cooperativas de Producción',                            'personas' => ['moral']],
        '621' => ['nombre' => 'Incorporación Fiscal',                                             'personas' => ['fisica']],
        '622' => ['nombre' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',         'personas' => ['fisica']],
        '623' => ['nombre' => 'Opcional para Grupos de Sociedades',                               'personas' => ['moral']],
        '624' => ['nombre' => 'Coordinados',                                                      'personas' => ['moral']],
        '625' => ['nombre' => 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', 'personas' => ['fisica']],
        '626' => ['nombre' => 'Régimen Simplificado de Confianza (RESICO)',                       'personas' => ['fisica', 'moral']],
    ],

    /*
    |--------------------------------------------------------------------------
    | c_UsoCFDI
    |--------------------------------------------------------------------------
    | Para una membresía de coworking lo habitual es G03 «Gastos en general».
    | Se deja el catálogo con las claves de uso corriente para que nadie tenga
    | que salirse del sistema a buscarlas.
    */
    'usos_cfdi' => [
        'G01' => 'Adquisición de mercancías',
        'G02' => 'Devoluciones, descuentos o bonificaciones',
        'G03' => 'Gastos en general',
        'I01' => 'Construcciones',
        'I02' => 'Mobiliario y equipo de oficina por inversiones',
        'I03' => 'Equipo de transporte',
        'I04' => 'Equipo de cómputo y accesorios',
        'I08' => 'Otra maquinaria y equipo',
        'D01' => 'Honorarios médicos, dentales y gastos hospitalarios',
        'D02' => 'Gastos médicos por incapacidad o discapacidad',
        'D03' => 'Gastos funerales',
        'D04' => 'Donativos',
        'D10' => 'Pagos por servicios educativos (colegiaturas)',
        'S01' => 'Sin efectos fiscales',
        'CP01' => 'Pagos',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uso por omisión
    |--------------------------------------------------------------------------
    */
    'uso_cfdi_predeterminado' => 'G03',

    /*
    |--------------------------------------------------------------------------
    | A dónde escribir por una factura
    |--------------------------------------------------------------------------
    | La pantalla de datos fiscales tiene que decirle al miembro quién emite y
    | en cuánto tiempo, porque no lo emite este sistema.
    */
    'contacto_facturacion' => env('NODICO_EMAIL_FACTURACION', 'facturacion@nodico.com.mx'),
    'dias_habiles_emision' => 5,

];
