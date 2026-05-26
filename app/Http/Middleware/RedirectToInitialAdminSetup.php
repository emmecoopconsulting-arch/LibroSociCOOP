<?php

namespace App\Http\Middleware;

use App\Services\InitialAdminSetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToInitialAdminSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            app(InitialAdminSetupService::class)->isRequired()
            && (! $request->routeIs('setup.*'))
        ) {
            return redirect()->route('setup.show');
        }

        return $next($request);
    }
}
