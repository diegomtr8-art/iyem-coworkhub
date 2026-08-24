<?php

use App\Http\Controllers\AnunciosController;
use App\Http\Controllers\CheckinAdminController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EspaciosController;
use App\Http\Controllers\EventosController;
use App\Http\Controllers\FacturasController;
use App\Http\Controllers\MiembrosController;
use App\Http\Controllers\PlanesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\ReservasController;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboard;
use App\Http\Controllers\Portal\ReservasController as PortalReservas;
use App\Http\Controllers\Portal\SuscripcionController;
use App\Http\Controllers\Portal\FacturasController as PortalFacturas;
use App\Http\Controllers\Portal\CheckinController as PortalCheckin;
use Illuminate\Support\Facades\Route;

// --- SITIO PÚBLICO ---
Route::get('/',            [WelcomeController::class, 'index'])->name('home');
Route::get('/nosotros',    [WelcomeController::class, 'nosotros'])->name('nosotros');
Route::get('/membresias',  [WelcomeController::class, 'membresias'])->name('membresias');
Route::get('/eventos',     [WelcomeController::class, 'eventos'])->name('eventos');
Route::get('/actividades', [WelcomeController::class, 'eventos'])->name('actividades');
Route::post('/contacto',   [ContactoController::class, 'store'])->name('contacto.store');

// --- ADMINISTRACIÓN ---
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('planes', PlanesController::class)->except(['show']);
    Route::resource('espacios', EspaciosController::class)->except(['show']);

    Route::get('miembros', [MiembrosController::class, 'index'])->name('miembros.index');
    Route::get('miembros/{miembro}', [MiembrosController::class, 'show'])->name('miembros.show');
    Route::post('miembros/{miembro}/suscripcion', [MiembrosController::class, 'crearSuscripcion'])->name('miembros.suscripcion');
    Route::patch('miembros/{miembro}/faceid', [MiembrosController::class, 'toggleFaceId'])->name('miembros.faceid');
    Route::patch('miembros/{miembro}/companion', [MiembrosController::class, 'setCompanion'])->name('miembros.companion');

    Route::get('reservas', [ReservasController::class, 'index'])->name('reservas.index');
    Route::patch('reservas/{reserva}', [ReservasController::class, 'update'])->name('reservas.update');
    Route::delete('reservas/{reserva}', [ReservasController::class, 'destroy'])->name('reservas.destroy');

    Route::get('checkins', [CheckinAdminController::class, 'index'])->name('checkins.index');
    Route::post('checkins/entrada', [CheckinAdminController::class, 'entrada'])->name('checkins.entrada');
    Route::post('checkins/{checkin}/salida', [CheckinAdminController::class, 'salida'])->name('checkins.salida');

    Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
    Route::post('facturas/{factura}/pagar', [FacturasController::class, 'pagar'])->name('facturas.pagar');

    Route::get('anuncios', [AnunciosController::class, 'index'])->name('anuncios.index');
    Route::post('anuncios', [AnunciosController::class, 'store'])->name('anuncios.store');
    Route::put('anuncios/{anuncio}', [AnunciosController::class, 'update'])->name('anuncios.update');
    Route::delete('anuncios/{anuncio}', [AnunciosController::class, 'destroy'])->name('anuncios.destroy');

    // Eventos admin
    Route::get('admin/eventos', [EventosController::class, 'index'])->name('eventos.admin.index');
    Route::get('admin/eventos/crear', [EventosController::class, 'create'])->name('eventos.admin.create');
    Route::post('admin/eventos', [EventosController::class, 'store'])->name('eventos.admin.store');
    Route::get('admin/eventos/{evento}/editar', [EventosController::class, 'edit'])->name('eventos.admin.edit');
    Route::patch('admin/eventos/{evento}', [EventosController::class, 'update'])->name('eventos.admin.update');
    Route::delete('admin/eventos/{evento}', [EventosController::class, 'destroy'])->name('eventos.admin.destroy');

    Route::get('reportes', [ReportesController::class, 'index'])->name('reportes.index');
});

// --- PORTAL MIEMBRO ---
Route::middleware(['auth', 'miembro'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalDashboard::class, 'index'])->name('dashboard');

    Route::get('reservar', [PortalReservas::class, 'create'])->name('reservar');
    Route::post('reservar', [PortalReservas::class, 'store'])->name('reservar.store');
    Route::get('mis-reservas', [PortalReservas::class, 'index'])->name('reservas');
    Route::delete('mis-reservas/{reserva}', [PortalReservas::class, 'destroy'])->name('reservas.cancel');

    Route::get('mi-suscripcion', [SuscripcionController::class, 'index'])->name('suscripcion');
    Route::get('mis-facturas', [PortalFacturas::class, 'index'])->name('facturas');

    Route::post('checkin/entrada', [PortalCheckin::class, 'entrada'])->name('checkin.entrada');
    Route::post('checkin/salida', [PortalCheckin::class, 'salida'])->name('checkin.salida');
});

// Profile
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
