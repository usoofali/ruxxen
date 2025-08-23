<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SystemConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckSystemSetup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip setup check for setup-related routes
        if ($request->is('setup*') || $request->is('production/setup*')) {
            return $next($request);
        }

        // Check if system_configurations table exists
        if (!Schema::hasTable('system_configurations')) {
            return redirect()->route('setup.welcome');
        }

        // Check if system is configured
        if (!SystemConfiguration::isConfigured()) {
            return redirect()->route('setup.welcome');
        }

        return $next($request);
    }
}
