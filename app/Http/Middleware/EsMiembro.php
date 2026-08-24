<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EsMiembro
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->tipo !== 'miembro') {
            return redirect()->route('dashboard');
        }
        return $next($request);
    }
}
