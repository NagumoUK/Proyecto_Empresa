<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagementFeatureIsActive
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Feature::globally()->active($feature), 404);

        return $next($request);
    }
}
