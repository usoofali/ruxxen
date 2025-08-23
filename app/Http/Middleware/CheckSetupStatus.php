<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ProductionSetupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckSetupStatus
{
    public function __construct(
        private ProductionSetupService $setupService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if setup is required
            $setupRequired = $this->setupService->isSetupRequired();
            
            // Get current route
            $currentRoute = $request->route();
            $currentRouteName = $currentRoute ? $currentRoute->getName() : null;
            
            // Define setup-related routes that should be accessible during setup
            $setupRoutes = [
                'setup.index',
                'setup.run',
                'setup.progress',
                'setup.reset',
            ];
            
            // If setup is required and user is not on a setup route, redirect to setup
            if ($setupRequired && !in_array($currentRouteName, $setupRoutes)) {
                Log::info('Redirecting to setup page - system not configured', [
                    'current_route' => $currentRouteName,
                    'setup_required' => $setupRequired,
                ]);
                
                return redirect()->route('setup.index');
            }
            
            // If setup is not required and user is on setup route, redirect to dashboard
            if (!$setupRequired && in_array($currentRouteName, $setupRoutes)) {
                Log::info('Redirecting to dashboard - system already configured', [
                    'current_route' => $currentRouteName,
                    'setup_required' => $setupRequired,
                ]);
                
                return redirect()->route('dashboard');
            }
            
        } catch (\Exception $e) {
            Log::error('Setup status check failed: ' . $e->getMessage(), [
                'exception' => $e,
                'current_route' => $request->route()?->getName(),
            ]);
            
            // In case of error, assume setup is required and redirect to setup
            if (!in_array($request->route()?->getName(), ['setup.index', 'setup.run', 'setup.progress'])) {
                return redirect()->route('setup.index');
            }
        }
        
        return $next($request);
    }
}
