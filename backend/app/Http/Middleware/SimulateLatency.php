<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slows every API response down by API_DELAY_MS (.env), so loading
 * spinners, skeletons and race conditions are visible while building
 * the React app. Off (0) by default.
 */
class SimulateLatency
{
    public function handle(Request $request, Closure $next): Response
    {
        $delay = (int) config('app.api_delay_ms');
        if ($delay > 0) {
            usleep($delay * 1000);
        }

        return $next($request);
    }
}
