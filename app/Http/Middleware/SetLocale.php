<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pick the best Accept-Language match; first entry ('nl') is the fallback.
        app()->setLocale($request->getPreferredLanguage(['nl', 'en']));

        return $next($request);
    }
}
