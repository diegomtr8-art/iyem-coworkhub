<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\ConsentimientoController;
use App\Http\Controllers\Auth\DesafioDosFactoresController;
use App\Http\Controllers\Auth\DosFactoresController;
use App\Http\Controllers\Auth\EnlaceMagicoController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\SeguridadController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    // El propio formulario tiene su límite: sin él, el alta es un generador de
    // correos gratuito hacia cualquier dirección.
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:registro');

    // B — Destino común del alta nueva y del correo ya registrado. Que sea la
    // misma URL para los dos casos es justo lo que impide averiguar cuál fue.
    Route::get('registro/revisa-tu-correo', [RegisteredUserController::class, 'revisaTuCorreo'])
        ->name('registro.revisa-tu-correo');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // El límite fino —por cuenta y por IP, con retraso creciente— vive en
    // `LoginRequest`. Este de aquí es solo un tope de fuerza bruta a lo bestia.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:acceso');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:recuperacion')
        ->name('password.email');

    /*
     * C — Acceso con proveedor externo.
     *
     * `{proveedor}` va acotado por expresion regular ademas de por la lista
     * blanca del controlador: sin eso, el segmento de la URL decide que driver
     * de Socialite se instancia.
     */
    Route::get('acceso/{proveedor}', [OAuthController::class, 'redirigir'])
        ->whereIn('proveedor', ['google'])
        ->middleware('throttle:acceso')
        ->name('oauth.redirigir');

    Route::get('acceso/{proveedor}/retorno', [OAuthController::class, 'retorno'])
        ->whereIn('proveedor', ['google'])
        ->middleware('throttle:acceso')
        ->name('oauth.retorno');

    /*
     * C — Enlace magico. El limite es el mismo que el de recuperacion: los dos
     * mandan al buzon de otra persona un enlace con poder sobre su cuenta.
     */
    Route::post('enlace-magico', [EnlaceMagicoController::class, 'enviar'])
        ->middleware('throttle:recuperacion')
        ->name('enlace-magico.enviar');

    Route::get('enlace-magico/enviado', [EnlaceMagicoController::class, 'enviado'])
        ->name('enlace-magico.enviado');

    Route::get('enlace-magico/{token}', [EnlaceMagicoController::class, 'entrar'])
        ->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:acceso')
        ->name('enlace-magico.entrar');

    /*
     * D — Desafio del segundo factor.
     *
     * Va en el grupo `guest` porque entre la contrasena y el codigo **no hay
     * sesion iniciada**: solo un identificador en la sesion. El limite es el
     * mismo del acceso, porque es exactamente eso.
     */
    Route::get('dos-factores/desafio', [DesafioDosFactoresController::class, 'mostrar'])
        ->name('dos-factores.desafio');

    Route::post('dos-factores/desafio', [DesafioDosFactoresController::class, 'verificar'])
        ->middleware('throttle:acceso');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:recuperacion')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:reenvio')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:acceso');

    // B — Cambiar la contraseña cierra las demás sesiones, así que va detrás de
    // `password.confirm` como el resto de acciones delicadas.
    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('password.confirm')
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    /*
     * E — Consentimiento.
     *
     * **Sin** `consentimiento.vigente`, igual que la pantalla de cuenta
     * suspendida: si la ruta que es destino de la redireccion llevara el
     * middleware que redirige, se llamaria a si misma para siempre.
     */
    Route::get('consentimiento', [ConsentimientoController::class, 'mostrar'])
        ->name('consentimiento');

    Route::post('consentimiento', [ConsentimientoController::class, 'guardar'])
        ->name('consentimiento.guardar');

    /*
     * A.5 — Cuenta suspendida.
     *
     * **Sin** `no.suspendida`, y no por descuido: si esta ruta llevara el
     * middleware que trae hasta aqui, se redirigiria a si misma para siempre.
     * Es el bucle de A.1 con otro disfraz.
     *
     * A quien no este suspendido se le manda a su portal, que si lo acepta: un
     * salto y se acabo.
     */
    Route::get('cuenta-suspendida', function (\Illuminate\Http\Request $peticion) {
        $usuario = $peticion->user();

        if ($usuario->estado !== \App\Enums\EstadoCuenta::Suspendida) {
            return redirect()->route($usuario->rutaInicio() ?? 'home');
        }

        return Inertia::render('Auth/CuentaSuspendida', [
            'motivo' => $usuario->estado->descripcion(),
        ]);
    })->name('cuenta.suspendida');

    // B — «Mi seguridad»: sesiones abiertas y bitácora propia. Fuera de los dos
    // portales a propósito: la necesitan por igual el equipo y los miembros, y
    // no cambia según el rol.
    Route::get('seguridad', [SeguridadController::class, 'index'])->name('seguridad');

    /*
     * D — Alta y baja del segundo factor.
     *
     * Todo detras de `password.confirm`: activarlo o quitarlo con una sesion
     * olvidada en un equipo prestado seria regalar justo lo que protege.
     */
    Route::middleware('password.confirm')->group(function () {
        Route::get('dos-factores', [DosFactoresController::class, 'crear'])
            ->name('dos-factores.crear');

        Route::post('dos-factores', [DosFactoresController::class, 'confirmar'])
            ->middleware('throttle:acceso')
            ->name('dos-factores.confirmar');

        Route::post('dos-factores/codigos', [DosFactoresController::class, 'regenerarCodigos'])
            ->name('dos-factores.codigos');

        Route::delete('dos-factores', [DosFactoresController::class, 'destruir'])
            ->name('dos-factores.destruir');
    });

    Route::middleware('password.confirm')->group(function () {
        Route::delete('seguridad/sesiones', [SeguridadController::class, 'cerrarOtras'])
            ->name('seguridad.cerrar-otras');

        Route::delete('seguridad/sesiones/{sesion}', [SeguridadController::class, 'cerrarUna'])
            ->name('seguridad.cerrar-una');

        Route::delete('seguridad/identidades/{identidad}', [SeguridadController::class, 'desvincular'])
            ->name('seguridad.desvincular');

        Route::delete('seguridad/dispositivos/{dispositivo}', [SeguridadController::class, 'olvidarDispositivo'])
            ->name('seguridad.olvidar-dispositivo');
    });
});
