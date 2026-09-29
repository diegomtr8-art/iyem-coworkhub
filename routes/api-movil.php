<?php

/*
|--------------------------------------------------------------------------
| API de la app móvil — /api/v1
|--------------------------------------------------------------------------
| El contrato completo, con la forma de cada respuesta y su permiso, está en
| docs/API-MOVIL.md. Aquí solo se cablea.
|
| Dos caras (§2): el grupo `miembro` es el portal; el grupo `reportes`, solo
| lectura para administración. Ninguna ruta recibe el id de un usuario: el
| dueño es siempre quien trae el token.
*/

use App\Http\Controllers\Api\Movil\AccesoController;
use App\Http\Controllers\Api\Movil\AsesoriasController;
use App\Http\Controllers\Api\Movil\AvisosController;
use App\Http\Controllers\Api\Movil\CredencialController;
use App\Http\Controllers\Api\Movil\EspaciosController;
use App\Http\Controllers\Api\Movil\InicioController;
use App\Http\Controllers\Api\Movil\MembresiaController;
use App\Http\Controllers\Api\Movil\PagosController;
use App\Http\Controllers\Api\Movil\PublicoController;
use App\Http\Controllers\Api\Movil\ReportesController;
use App\Http\Controllers\Api\Movil\ReservasController;
use App\Http\Controllers\Api\Movil\YoController;
use Illuminate\Support\Facades\Route;

Route::middleware('movil.version')->group(function () {

    // ── Sin sesión ──────────────────────────────────────────────────────────
    Route::get('estado', [PublicoController::class, 'estado'])->middleware('throttle:120,1')->name('estado');
    Route::get('planes', [PublicoController::class, 'planes'])->middleware('throttle:120,1')->name('planes');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Los mismos límites que el acceso web: `acceso` por IP más el retraso
        // creciente por cuenta de `ControlDeIntentos` dentro de LoginRequest.
        Route::post('token', [AccesoController::class, 'token'])->middleware('throttle:acceso')->name('token');
        Route::post('dos-factores', [AccesoController::class, 'dosFactores'])->middleware('throttle:acceso')->name('dos-factores');
        Route::post('google', [AccesoController::class, 'google'])->middleware('throttle:acceso')->name('google');
        Route::post('enlace-magico', [AccesoController::class, 'pedirEnlace'])->middleware('throttle:recuperacion')->name('enlace');
        Route::post('enlace-magico/canjear', [AccesoController::class, 'canjearEnlace'])->middleware('throttle:acceso')->name('enlace.canjear');
    });

    // ── Con sesión, cualquier cara ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:movil'])->group(function () {
        Route::post('auth/salir', [AccesoController::class, 'salir'])->name('auth.salir');

        Route::post('auth/verificacion/reenviar', [AccesoController::class, 'reenviarVerificacion'])
            ->middleware(['movil.estado:solo-suspension', 'throttle:reenvio'])
            ->name('auth.verificacion');

        // «Yo» básico: la app lo necesita para saber qué cara pintar y para las
        // pantallas de correo sin verificar o consentimiento pendiente.
        Route::get('yo', [YoController::class, 'mostrar'])->middleware('movil.estado:solo-suspension')->name('yo');

        Route::middleware('movil.estado:sin-consentimiento')->group(function () {
            Route::get('yo/consentimiento', [YoController::class, 'consentimiento'])->name('yo.consentimiento');
            Route::post('yo/consentimiento', [YoController::class, 'aceptarConsentimiento'])->name('yo.consentimiento.aceptar');
        });

        Route::middleware('movil.estado:solo-suspension')->group(function () {
            Route::get('yo/dispositivos', [YoController::class, 'dispositivos'])->name('yo.dispositivos');
            Route::delete('yo/dispositivos/{id}', [YoController::class, 'revocarDispositivo'])->whereNumber('id')->name('yo.dispositivos.revocar');
            Route::put('yo/push', [YoController::class, 'registrarPush'])->name('yo.push');
            Route::delete('yo/push', [YoController::class, 'quitarPush'])->name('yo.push.quitar');
        });
    });

    // ── Cara «miembro»: el portal ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'movil.cara:miembro', 'movil.estado', 'throttle:movil'])->group(function () {
        Route::patch('yo', [YoController::class, 'actualizar'])->name('yo.actualizar');
        Route::post('yo/foto', [YoController::class, 'foto'])->name('yo.foto');
        Route::delete('yo/foto', [YoController::class, 'borrarFoto'])->name('yo.foto.borrar');
        Route::get('yo/datos-fiscales', [YoController::class, 'datosFiscales'])->name('yo.fiscales');
        Route::put('yo/datos-fiscales', [YoController::class, 'guardarDatosFiscales'])->name('yo.fiscales.guardar');
        Route::delete('yo', [YoController::class, 'borrarCuenta'])->name('yo.borrar');

        Route::get('inicio', InicioController::class)->name('inicio');

        Route::get('espacios', [EspaciosController::class, 'index'])->name('espacios');
        Route::get('espacios/{espacio}/disponibilidad', [EspaciosController::class, 'periodo'])->whereNumber('espacio')->name('espacios.periodo');
        Route::get('espacios/{espacio}/disponibilidad/{fecha}', [EspaciosController::class, 'dia'])
            ->whereNumber('espacio')->where('fecha', '\d{4}-\d{2}-\d{2}')->name('espacios.dia');

        Route::get('reservas', [ReservasController::class, 'index'])->name('reservas');
        Route::get('reservas/{id}', [ReservasController::class, 'mostrar'])->whereNumber('id')->name('reservas.mostrar');
        Route::post('reservas', [ReservasController::class, 'crear'])
            ->middleware(['throttle:movil-reserva', 'movil.idempotente'])->name('reservas.crear');
        Route::post('reservas/{id}/cancelar', [ReservasController::class, 'cancelar'])->whereNumber('id')->name('reservas.cancelar');

        Route::get('membresia', [MembresiaController::class, 'mostrar'])->name('membresia');
        Route::post('membresia/acompanante', [MembresiaController::class, 'asignarAcompanante'])->name('membresia.acompanante');
        Route::delete('membresia/acompanante', [MembresiaController::class, 'quitarAcompanante'])->name('membresia.acompanante.quitar');
        Route::post('membresia/renovacion/cancelar', [MembresiaController::class, 'cancelarRenovacion'])->name('membresia.renovacion.cancelar');
        Route::post('membresia/renovacion/reactivar', [MembresiaController::class, 'reactivarRenovacion'])->name('membresia.renovacion.reactivar');

        Route::get('asesorias', [AsesoriasController::class, 'index'])->name('asesorias');
        Route::post('asesorias', [AsesoriasController::class, 'crear'])->middleware('movil.idempotente')->name('asesorias.crear');
        Route::post('asesorias/{id}/cancelar', [AsesoriasController::class, 'cancelar'])->whereNumber('id')->name('asesorias.cancelar');

        Route::get('planes/contratables', [PagosController::class, 'contratables'])->name('planes.contratables');
        Route::post('pagos/tarjeta', [PagosController::class, 'prepararTarjeta'])->middleware('movil.idempotente')->name('pagos.tarjeta');
        Route::post('pagos/tarjeta/suscribir', [PagosController::class, 'suscribir'])->middleware('movil.idempotente')->name('pagos.tarjeta.suscribir');
        Route::get('pagos/tarjeta/estado', [PagosController::class, 'estadoTarjeta'])->name('pagos.tarjeta.estado');
        // Paso 6: la tarjeta se paga en la web de Nódico, no dentro de la app.
        Route::post('pagos/tarjeta/enlace', [\App\Http\Controllers\PagoDesdeAppController::class, 'enlace'])
            ->middleware('throttle:10,1')
            ->name('pagos.tarjeta.enlace');
        Route::post('pagos/referencia', [PagosController::class, 'referencia'])->middleware('movil.idempotente')->name('pagos.referencia');
        Route::get('pagos', [PagosController::class, 'index'])->name('pagos');
        Route::get('pagos/{id}', [PagosController::class, 'mostrar'])->whereNumber('id')->name('pagos.mostrar');
        Route::post('pagos/{id}/ya-pague', [PagosController::class, 'yaPague'])->whereNumber('id')->name('pagos.ya-pague');
        Route::get('cobros', [PagosController::class, 'cobros'])->name('cobros');
        Route::get('facturas', [PagosController::class, 'facturas'])->name('facturas');
        Route::get('facturas/{id}/{formato}', [PagosController::class, 'descargar'])
            ->whereNumber('id')->whereIn('formato', ['pdf', 'xml'])->middleware('throttle:movil-descargas')->name('facturas.descargar');

        Route::get('avisos', [AvisosController::class, 'index'])->name('avisos');
        Route::post('avisos/comunicados/{id}/leido', [AvisosController::class, 'leido'])->whereNumber('id')->name('avisos.leido');

        Route::get('credencial', [CredencialController::class, 'mostrar'])->name('credencial');
        Route::post('credencial/renovar', [CredencialController::class, 'renovar'])->name('credencial.renovar');
    });

    // ── Cara «reportes»: solo lectura, administración ───────────────────────
    Route::middleware(['auth:sanctum', 'movil.cara:reportes', 'movil.estado:sin-consentimiento', 'throttle:movil'])
        ->prefix('reportes')->name('reportes.')->group(function () {
            Route::get('resumen', [ReportesController::class, 'resumen'])->name('resumen');
            Route::get('ocupacion', [ReportesController::class, 'ocupacion'])->name('ocupacion');
            Route::get('consumo', [ReportesController::class, 'consumo'])->name('consumo');
            Route::get('ingresos', [ReportesController::class, 'ingresos'])->name('ingresos');
            Route::get('no-show', [ReportesController::class, 'noShow'])->name('no-show');
            Route::get('en-riesgo', [ReportesController::class, 'enRiesgo'])->name('en-riesgo');
            Route::get('{informe}/csv', [ReportesController::class, 'csv'])
                ->whereIn('informe', ['ocupacion', 'consumo', 'ingresos', 'no_show', 'en_riesgo'])
                ->middleware('throttle:movil-descargas')->name('csv');
        });
});
