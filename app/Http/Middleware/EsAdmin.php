<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->tipo !== 'admin') {
            return redirect()->route('portal.dashboard');
        }
        return $next($request);
    }
}
