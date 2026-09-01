<?php

use App\Http\Controllers\Api\AccesoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints del agente de acceso (Fase 2 — Smart Pass)
|--------------------------------------------------------------------------
| Servicio a servicio: sin sesión web ni CSRF. Su autenticidad la da la firma
| HMAC del agente, que `VerificaFirmaDelAgente` comprueba (se aplica al grupo
| desde bootstrap/app.php). El prefijo `api/acceso` lo pone ese mismo grupo, así
| que aquí las rutas cuelgan de la raíz del grupo.
*/

Route::post('/eventos', [AccesoController::class, 'eventos'])->name('eventos');
Route::post('/alerta', [AccesoController::class, 'alerta'])->name('alerta');
Route::post('/latido', [AccesoController::class, 'latido'])->name('latido');

// Cola de órdenes hacia el agente (Fase 4: abrir puerta desde el panel).
Route::post('/comandos', [AccesoController::class, 'comandos'])->name('comandos');
Route::post('/comandos/{comando}/resultado', [AccesoController::class, 'resultadoComando'])->name('comandos.resultado');
