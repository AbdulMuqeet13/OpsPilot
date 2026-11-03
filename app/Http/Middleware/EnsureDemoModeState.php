<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoModeState
{
    /**
     * Only let the request through when demo mode matches the required state ("on" or "off").
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredState): Response
    {
        $demoModeIsOn = (bool) config('demo.enabled');

        abort_unless($demoModeIsOn === ($requiredState === 'on'), 404);

        return $next($request);
    }
}
