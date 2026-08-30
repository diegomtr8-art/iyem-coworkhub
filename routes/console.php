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
| ## Por qué cada una escribe a `tareas.log`
|
| `schedule:run` **no vuelca a su salida lo que imprimen los comandos que
| ejecuta**: los corre y descarta su salida. Eso significa que el log del cron
| se queda vacío aunque todo funcione, y entonces no hay forma de distinguir
| «corrió y no había nada que hacer» de «el cron no existe».
|
| Se comprobó en staging el 01/09/2026: el cron llevaba diez minutos corriendo
| correctamente y `cron.log` seguía en cero bytes. Con `appendOutputTo` cada
| pasada deja constancia, y verificar el despliegue pasa a ser mirar un archivo.
|
| `withoutOverlapping` en todas: si una pasada se atasca, la siguiente no se le
| monta encima. Las tres son idempotentes de por sí, pero eso protege del
| error, no del desperdicio.
*/

$bitacoraDeTareas = storage_path('logs/tareas.log');

// Fase 1.5 — abre el ciclo de las suscripciones que cumplen aniversario.
// A las 00:05 para que el día ya haya cambiado en la zona horaria de Mérida.
Schedule::command('nodico:reiniciar-ciclos')
    ->dailyAt('00:05')
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo($bitacoraDeTareas);

// Fase 1.6 — marca las reservas que pasaron sin check-in.
// Cada media hora: una reserva termina a en punto o y media, y con el margen
// de cortesía de 15 min queda marcada dentro de la hora siguiente.
Schedule::command('nodico:marcar-no-show')
    ->everyThirtyMinutes()
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo($bitacoraDeTareas);

// Vigilancia del libro: sin `--arreglar`, solo informa. Si los contadores
// divergen del libro, el comando sale con código de error y queda escrito en
// `tareas.log`. Corregirlo es una decisión, no algo que deba pasar de noche y
// en silencio.
Schedule::command('nodico:reconstruir-saldos')
    ->dailyAt('03:00')
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo($bitacoraDeTareas);

// Fase 4.H — el emprendedor de la semana rota los lunes a primera hora, para
// que quien entre el lunes ya vea al nuevo.
Schedule::command('nodico:rotar-emprendedor')
    ->weeklyOn(1, '06:00')
    ->timezone('America/Merida')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo($bitacoraDeTareas);
