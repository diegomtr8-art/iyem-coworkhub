<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->limitarContacto();

        Vite::prefetch(concurrency: 3);
    }

    /**
     * BE-02 — el límite anterior era `throttle:5,1` por IP, y todo el coworking
     * sale por la misma: cinco envíos en un minuto desde el propio espacio
     * bloqueaban a los demás. Se combina un margen amplio por IP con uno
     * estrecho por correo, que es lo que de verdad identifica a quien envía.
     */
    private function limitarContacto(): void
    {
        RateLimiter::for('contacto', function (Request $request) {
            $correo = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(10, 30)->by('ip:' . $request->ip()),
                Limit::perMinutes(10, 3)->by('correo:' . ($correo ?: $request->ip())),
            ];
        });
    }
}
