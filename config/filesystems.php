<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
        | Imágenes subidas desde el módulo «Página Web» (docs/CMS-PAGINA-WEB.md).
        |
        | Tienen que caer en la carpeta que sirve el navegador, y esa carpeta no
        | es la misma en todos lados: en local es `public/`, pero en Hostinger
        | el document root es la raíz del proyecto (`public_html/`, con su
        | propio index.php) y `public_path()` apunta a `public_html/public`,
        | que no se sirve. Por eso el disco `public` + `storage:link` no
        | funciona allí: `/storage` cae en el storage/ de la aplicación (403).
        |
        | Un index.php en la raíz del proyecto es la marca de ese layout plano.
        | `NODICO_MEDIOS_RAIZ` lo fuerza si algún día hace falta.
        |
        | `deploy_prueba.py` no sincroniza esta carpeta, así que un despliegue
        | nunca pisa lo que subió la coordinación. Ver docs/DEPLOY.md.
        */
        'medios' => [
            'driver' => 'local',
            'root' => env('NODICO_MEDIOS_RAIZ')
                ?: (file_exists(base_path('index.php')) ? base_path('medios') : public_path('medios')),
            'url' => '/medios',
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
