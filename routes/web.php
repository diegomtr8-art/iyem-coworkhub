<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AnunciosController;
use App\Http\Controllers\AsesoriasAdminController;
use App\Http\Controllers\SalonesController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CheckinAdminController;
use App\Http\Controllers\DaypassInteriorController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatosPersonalesController;
use App\Http\Controllers\EmprendedoresController;
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
use App\Http\Controllers\Portal\CheckoutController;
use App\Http\Controllers\Portal\SuscripcionController;
use App\Http\Controllers\Portal\CheckinController as PortalCheckin;
use App\Http\Controllers\Portal\PerfilController as PortalPerfil;
use App\Http\Controllers\Portal\DatosFiscalesController as PortalDatosFiscales;
use App\Http\Controllers\Portal\AsesoriasController as PortalAsesorias;
use App\Http\Controllers\Portal\AccesosController as PortalAccesos;
use Illuminate\Support\Facades\Route;

// --- SITIO PÚBLICO ---
Route::get('/',            [WelcomeController::class, 'index'])->name('home');
Route::get('/nosotros',    [WelcomeController::class, 'nosotros'])->name('nosotros');
Route::get('/membresias',  [WelcomeController::class, 'membresias'])->name('membresias');
Route::get('/eventos',     [WelcomeController::class, 'salones'])->name('eventos');
Route::get('/actividades', [WelcomeController::class, 'comunidad'])->name('actividades');
Route::post('/contacto',   [ContactoController::class, 'store'])
    ->middleware('throttle:contacto')
    ->name('contacto.store');

Route::get('/aviso-de-privacidad', [WelcomeController::class, 'privacidad'])->name('privacidad');
Route::get('/terminos',            [WelcomeController::class, 'terminos'])->name('terminos');

// robots.txt dinamico: en staging se bloquea la indexacion completa.
Route::get('/robots.txt', function () {
    if (app()->environment('staging', 'local')) {
        $lineas = ['User-agent: *', 'Disallow: /'];
    } else {
        $lineas = [
            'User-agent: *',
            'Disallow: /dashboard',
            'Disallow: /portal',
            'Disallow: /profile',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
        ];
    }

    return response(implode(PHP_EOL, $lineas) . PHP_EOL, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('robots');

// SEO-05 — sitemap con las cinco publicas mas las legales.
Route::get('/sitemap.xml', function () {
    $host = config('nodico.host_canonico') ?: url('/');
    $host = rtrim($host, '/');

    $paginas = [
        ['home',        '1.0', 'weekly'],
        ['membresias',  '0.9', 'weekly'],
        ['eventos',     '0.8', 'monthly'],
        ['actividades', '0.8', 'weekly'],
        ['nosotros',    '0.7', 'monthly'],
        ['privacidad',  '0.3', 'yearly'],
        ['terminos',    '0.3', 'yearly'],
    ];

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    foreach ($paginas as [$ruta, $prioridad, $frecuencia]) {
        $camino = parse_url(route($ruta), PHP_URL_PATH) ?: '/';
        $xml .= '  <url>' . PHP_EOL
            . '    <loc>' . htmlspecialchars($host . $camino, ENT_XML1) . '</loc>' . PHP_EOL
            . '    <changefreq>' . $frecuencia . '</changefreq>' . PHP_EOL
            . '    <priority>' . $prioridad . '</priority>' . PHP_EOL
            . '  </url>' . PHP_EOL;
    }

    $xml .= '</urlset>' . PHP_EOL;

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

// --- PANEL OPERATIVO (administracion y recepcion) ---
// A.1 — `portal:operativo` aborta con 403 en vez de redirigir al otro portal.
// A.3 — dentro del panel, cada seccion declara su permiso con `can:`; recepcion
// entra al panel pero no a planes, espacios, facturacion, eventos ni reportes.
// B — La sesion del panel caduca antes que la del portal: se usa en una
// recepcion, en un equipo compartido y a la vista de quien pase.
Route::middleware(['auth', 'verified', 'portal:operativo', 'no.suspendida', 'consentimiento', 'inactividad:60'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:gestionar-planes')->group(function () {
        Route::resource('planes', PlanesController::class)->except(['show']);
    });

    Route::middleware('can:gestionar-espacios')->group(function () {
        Route::resource('espacios', EspaciosController::class)->except(['show']);
    });

    Route::middleware('can:ver-miembros')->group(function () {
        Route::get('miembros', [MiembrosController::class, 'index'])->name('miembros.index');
        Route::get('miembros/{miembro}', [MiembrosController::class, 'show'])->name('miembros.show');
    });

    // 3.2 - Ajuste de horas y notas. Recepcion tambien: es operacion de
    // mostrador, y es seguro darselo porque siempre exige motivo y siempre
    // acaba en un movimiento del libro, nunca editando un contador.
    Route::middleware('can:ajustar-horas')->group(function () {
        Route::post('miembros/{miembro}/ajustar-horas', [MiembrosController::class, 'ajustarHoras'])->name('miembros.ajustar-horas');
        Route::patch('miembros/{miembro}/notas', [MiembrosController::class, 'notas'])->name('miembros.notas');
    });

    // 3.3 - Membresias: alta, renovacion, cambio de plan y suspension.
    Route::middleware('can:editar-miembros')->group(function () {
        Route::post('miembros/{miembro}/suscripcion', [MiembrosController::class, 'crearSuscripcion'])->name('miembros.suscripcion');
        Route::post('miembros/{miembro}/cambiar-plan', [MiembrosController::class, 'cambiarPlan'])->name('miembros.cambiar-plan');
        Route::post('miembros/{miembro}/renovar', [MiembrosController::class, 'renovar'])->name('miembros.renovar');
        Route::post('miembros/{miembro}/suspender', [MiembrosController::class, 'suspender'])->name('miembros.suspender');
        Route::post('miembros/{miembro}/reactivar', [MiembrosController::class, 'reactivar'])->name('miembros.reactivar');
        Route::patch('miembros/{miembro}/faceid', [MiembrosController::class, 'toggleFaceId'])->name('miembros.faceid');
        Route::patch('miembros/{miembro}/companion', [MiembrosController::class, 'setCompanion'])->name('miembros.companion');
    });

    // 3.1 - Buscador global de miembro, siempre a mano en la cabecera.
    Route::get('buscar-miembro', [DashboardController::class, 'buscar'])
        ->middleware(['can:ver-miembros', 'throttle:120,1'])
        ->name('buscar.miembro');

    // 3.4 - Agenda semanal de espacios.
    Route::middleware('can:gestionar-reservas')->group(function () {
        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('agenda/reservar', [AgendaController::class, 'store'])->name('agenda.reservar');
        Route::patch('agenda/reservas/{reserva}', [AgendaController::class, 'mover'])->name('agenda.mover');
        Route::delete('agenda/reservas/{reserva}', [AgendaController::class, 'cancelar'])->name('agenda.cancelar');
        Route::post('agenda/bloqueos', [AgendaController::class, 'bloquear'])->name('agenda.bloquear');
        Route::delete('agenda/bloqueos/{bloqueo}', [AgendaController::class, 'desbloquear'])->name('agenda.desbloquear');

        Route::get('reservas', [ReservasController::class, 'index'])->name('reservas.index');
        Route::patch('reservas/{reserva}', [ReservasController::class, 'update'])->name('reservas.update');
        Route::delete('reservas/{reserva}', [ReservasController::class, 'destroy'])->name('reservas.destroy');
    });

    // 3.5 - Salones para eventos.
    Route::middleware('can:gestionar-salones')->group(function () {
        Route::get('salones', [SalonesController::class, 'index'])->name('salones.index');
        Route::post('salones/cotizar', [SalonesController::class, 'cotizar'])->middleware('throttle:120,1')->name('salones.cotizar');
        Route::post('salones', [SalonesController::class, 'store'])->name('salones.store');
        Route::patch('salones/{salon}', [SalonesController::class, 'update'])->name('salones.update');
        Route::post('salones/{salon}/anticipo', [SalonesController::class, 'anticipo'])->name('salones.anticipo');
        Route::delete('salones/{salon}', [SalonesController::class, 'destroy'])->name('salones.destroy');
    });

    // 3.7 - Bandeja de solicitudes de asesoria.
    Route::middleware('can:gestionar-asesorias')->group(function () {
        Route::get('asesorias', [AsesoriasAdminController::class, 'index'])->name('asesorias.index');
        Route::post('asesorias/{asesoria}/confirmar', [AsesoriasAdminController::class, 'confirmar'])->name('asesorias.confirmar');
        Route::post('asesorias/{asesoria}/rechazar', [AsesoriasAdminController::class, 'rechazar'])->name('asesorias.rechazar');
        Route::post('asesorias/{asesoria}/realizada', [AsesoriasAdminController::class, 'realizada'])->name('asesorias.realizada');
    });

    // El catalogo de asesores es configuracion, no operacion: solo admin.
    Route::middleware('can:gestionar-catalogos')->group(function () {
        Route::get('asesores', [AsesoriasAdminController::class, 'asesores'])->name('asesores.index');
        Route::post('asesores', [AsesoriasAdminController::class, 'guardarAsesor'])->name('asesores.store');
        Route::patch('asesores/{asesor}', [AsesoriasAdminController::class, 'guardarAsesor'])->name('asesores.update');

        // 4.D — catalogo de temas de asesoria.
        Route::get('temas-asesoria', [AsesoriasAdminController::class, 'temas'])->name('temas.index');
        Route::post('temas-asesoria', [AsesoriasAdminController::class, 'guardarTema'])->name('temas.store');
        Route::patch('temas-asesoria/{tema}', [AsesoriasAdminController::class, 'guardarTema'])->name('temas.update');
    });

    Route::middleware('can:operar-checkins')->group(function () {
        Route::get('checkins', [CheckinAdminController::class, 'index'])->name('checkins.index');
        Route::post('checkins/entrada', [CheckinAdminController::class, 'entrada'])->name('checkins.entrada');
        Route::post('checkins/{checkin}/salida', [CheckinAdminController::class, 'salida'])->name('checkins.salida');

        // 4.E — day-pass gratuito del interior. Operación de mostrador.
        Route::get('daypass-interior', [DaypassInteriorController::class, 'index'])->name('daypass.index');
        Route::get('daypass-interior/buscar', [DaypassInteriorController::class, 'buscar'])->name('daypass.buscar');
        Route::post('daypass-interior', [DaypassInteriorController::class, 'registrar'])->name('daypass.registrar');
        Route::get('daypass-interior/exportar', [DaypassInteriorController::class, 'exportar'])->name('daypass.exportar');
    });

    Route::middleware('can:gestionar-facturacion')->group(function () {
        Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
        // Va antes del {factura} para que 'exportar' no se lea como un id.
        Route::get('facturas/exportar', [FacturasController::class, 'exportar'])->name('facturas.exportar');
        Route::post('facturas/{factura}/pagar', [FacturasController::class, 'pagar'])->name('facturas.pagar');
    });

    Route::middleware('can:gestionar-anuncios')->group(function () {
        Route::get('anuncios', [AnunciosController::class, 'index'])->name('anuncios.index');
        Route::post('anuncios', [AnunciosController::class, 'store'])->name('anuncios.store');
        Route::put('anuncios/{anuncio}', [AnunciosController::class, 'update'])->name('anuncios.update');
        Route::delete('anuncios/{anuncio}', [AnunciosController::class, 'destroy'])->name('anuncios.destroy');
    });

    // Eventos del sitio publico: es contenido de cara al exterior, no operacion diaria.
    Route::middleware('can:gestionar-eventos')->group(function () {
        Route::get('admin/eventos', [EventosController::class, 'index'])->name('eventos.admin.index');
        Route::get('admin/eventos/crear', [EventosController::class, 'create'])->name('eventos.admin.create');
        Route::post('admin/eventos', [EventosController::class, 'store'])->name('eventos.admin.store');
        Route::get('admin/eventos/{evento}/editar', [EventosController::class, 'edit'])->name('eventos.admin.edit');
        Route::patch('admin/eventos/{evento}', [EventosController::class, 'update'])->name('eventos.admin.update');
        Route::delete('admin/eventos/{evento}', [EventosController::class, 'destroy'])->name('eventos.admin.destroy');

        // 4.H — catalogo de emprendimientos y rotacion del destacado.
        Route::get('admin/emprendedores', [EmprendedoresController::class, 'index'])->name('emprendedores.index');
        Route::post('admin/emprendedores', [EmprendedoresController::class, 'guardar'])->name('emprendedores.store');
        Route::patch('admin/emprendedores/{emprendedor}', [EmprendedoresController::class, 'guardar'])->name('emprendedores.update');
        Route::post('admin/emprendedores/{emprendedor}/fijar', [EmprendedoresController::class, 'fijar'])->name('emprendedores.fijar');
    });

    Route::middleware('can:ver-reportes')->group(function () {
        Route::get('reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('reportes/exportar', [ReportesController::class, 'exportar'])->name('reportes.exportar');
    });

    // B — Bitacora completa de autenticacion.
    Route::middleware('can:ver-bitacora')->group(function () {
        Route::get('bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
    });
});

// --- PORTAL MIEMBRO ---
// A.2 — `verified` faltaba en este grupo, que es justo donde cae todo el mundo
// al registrarse: nadie verificaba su correo.
Route::middleware(['auth', 'verified', 'portal:miembro', 'no.suspendida', 'consentimiento', 'inactividad:240'])->prefix('portal')->name('portal.')->group(function () {
    // 2.1 — Inicio.
    Route::get('/', [PortalDashboard::class, 'index'])->name('dashboard');

    // 2.5 y 2.6 — Reservar y mis reservas.
    Route::get('reservar', [PortalReservas::class, 'create'])->name('reservar');
    Route::post('reservar', [PortalReservas::class, 'store'])->name('reservar.store');

    // Consulta de huecos. Va con `throttle` porque la pantalla la llama cada vez
    // que se cambia de espacio o de dia, y es la unica ruta del portal que se
    // pide sin intervencion directa de la persona.
    Route::get('disponibilidad', [PortalReservas::class, 'disponibilidad'])
        ->middleware('throttle:120,1')
        ->name('disponibilidad');

    Route::get('mis-reservas', [PortalReservas::class, 'index'])->name('reservas');
    Route::delete('mis-reservas/{reserva}', [PortalReservas::class, 'destroy'])->name('reservas.cancel');

    // 2.4 — Mi membresia.
    Route::get('mi-membresia', [SuscripcionController::class, 'index'])->name('suscripcion');

    // 4.A — Cobro dentro de Nódico (Stripe Elements). La activación la hace el
    // webhook; estas rutas solo preparan el pago y consultan el estado.
    Route::get('contratar/{plan}', [CheckoutController::class, 'mostrar'])->name('contratar');
    Route::post('contratar/{plan}', [CheckoutController::class, 'procesarSuscripcion'])->name('contratar.suscripcion');
    Route::get('pago/confirmando', [CheckoutController::class, 'confirmando'])->name('pago.confirmando');
    Route::get('pago/estado', [CheckoutController::class, 'estado'])->name('pago.estado');
    Route::post('membresia/cancelar-renovacion', [SuscripcionController::class, 'cancelarRenovacion'])->name('membresia.cancelar');
    Route::post('membresia/reactivar-renovacion', [SuscripcionController::class, 'reactivarRenovacion'])->name('membresia.reactivar');

    // Nodo Match — asignar/quitar al acompañante por su correo.
    Route::post('membresia/acompanante', [SuscripcionController::class, 'asignarAcompanante'])->name('membresia.acompanante');
    Route::delete('membresia/acompanante', [SuscripcionController::class, 'quitarAcompanante'])->name('membresia.acompanante.quitar');

    // 2.2 — Mi perfil.
    Route::get('mi-perfil', [PortalPerfil::class, 'edit'])->name('perfil');
    Route::patch('mi-perfil', [PortalPerfil::class, 'update'])->name('perfil.update');
    Route::post('mi-perfil/foto', [PortalPerfil::class, 'avatar'])->name('perfil.avatar');
    Route::delete('mi-perfil/foto', [PortalPerfil::class, 'borrarAvatar'])->name('perfil.avatar.destroy');

    // 2.3 — Datos fiscales. Dato personal sensible: cada lectura queda en bitacora.
    Route::get('datos-fiscales', [PortalDatosFiscales::class, 'edit'])->name('datos-fiscales');
    Route::put('datos-fiscales', [PortalDatosFiscales::class, 'update'])->name('datos-fiscales.update');

    // 2.7 — Asesoria IYEM.
    Route::get('asesoria', [PortalAsesorias::class, 'index'])->name('asesoria');
    Route::post('asesoria', [PortalAsesorias::class, 'store'])->name('asesoria.store');
    Route::delete('asesoria/{asesoria}', [PortalAsesorias::class, 'destroy'])->name('asesoria.cancel');

    // 2.8 — Accesos y pagos.
    // Los pagos viven aqui: `mis-facturas` era una segunda pantalla que ensenaba
    // lo mismo, y dos sitios para el mismo dato es un sitio de mas que mantener.
    Route::get('mis-accesos', [PortalAccesos::class, 'index'])->name('accesos');

    Route::post('checkin/entrada', [PortalCheckin::class, 'entrada'])->name('checkin.entrada');
    Route::post('checkin/salida', [PortalCheckin::class, 'salida'])->name('checkin.salida');
});

// Perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // E — Derecho de acceso: la persona se lleva sus datos sin pedirselos a
    // nadie. Con limite, porque genera un volcado completo por peticion.
    Route::get('/profile/mis-datos', [DatosPersonalesController::class, 'descargar'])
        ->middleware('throttle:6,1')
        ->name('datos.descargar');

    // Cambiar el correo pide la contrasena actual como campo del formulario;
    // la regla vive en ProfileUpdateRequest, que solo la exige cuando el correo
    // cambia de verdad.
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // B — Borrar la cuenta es irreversible: ademas del campo de contrasena que
    // ya pedia, va detras de password.confirm.
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('password.confirm')
        ->name('profile.destroy');
});

// F — Todas las pantallas de acceso comparten la prueba social del panel de
// marca. Va aqui y no en `HandleInertiaRequests` para no pagar dos consultas
// en cada peticion del resto del sitio.
Route::middleware(\App\Http\Middleware\CompartirMarcaDeAcceso::class)
    ->group(__DIR__.'/auth.php');

// Fase 4.A — webhook de Stripe. Sin auth (lo llama Stripe) y sin CSRF (excluido
// en bootstrap/app.php); la firma la verifica el propio controlador vía Cashier.
Route::post('stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
