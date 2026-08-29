<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas de Nódico
|--------------------------------------------------------------------------
| Las tres tienen que correr en el servidor. La línea de cron de Hostinger
| está en `docs/DEPLOY.md`, y **hay que comprobar que corre de verdad**: una
| tarea programada que nadie verifica es una tarea que no existe.
|
| `withoutOverlapping` en todas: si una pasada se atasca, la siguiente no se
| le monta encima. Las tres son idempotentes de por sí, pero eso protege del
| error, no del desperdicio.
*/

// Fase 1.5 — abre el ciclo de las suscripciones que cumplen aniversario.
// A las 00:05 para que el día ya haya cambiado en la zona horaria de Mérida.
Schedule::command('nodico:reiniciar-ciclos')
    ->dailyAt('00:05')
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer();

// Fase 1.6 — marca las reservas que pasaron sin check-in.
// Cada media hora: una reserva termina a en punto o y media, y con el margen
// de cortesía de 15 min queda marcada dentro de la hora siguiente.
Schedule::command('nodico:marcar-no-show')
    ->everyThirtyMinutes()
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer();

// Vigilancia del libro: sin `--arreglar`, solo informa. Si los contadores
// divergen del libro, el comando sale con código de error y el cron lo
// reporta. Corregirlo es una decisión, no algo que deba pasar de noche y en
// silencio.
Schedule::command('nodico:reconstruir-saldos')
    ->dailyAt('03:00')
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer();
