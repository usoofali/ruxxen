<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProductionSetupService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class SetupController extends Controller
{
    public function __construct(
        private ProductionSetupService $setupService
    ) {}

    /**
     * Show setup page
     */
    public function index(): View|RedirectResponse
    {
        // If system is already configured, redirect to dashboard
        if (!$this->setupService->isSetupRequired()) {
            return redirect()->route('dashboard');
        }

        $progress = $this->setupService->getSetupProgress();
        
        return view('setup.index', compact('progress'));
    }

    /**
     * Run production setup
     */
    public function runSetup(Request $request): JsonResponse
    {
        try {
            // Check if setup is already completed
            if (!$this->setupService->isSetupRequired()) {
                return response()->json([
                    'success' => true,
                    'message' => 'System is already configured',
                    'redirect' => route('dashboard'),
                ]);
            }

            // Run the setup
            $results = $this->setupService->runSetup();

            if ($results['success']) {
                Log::info('Production setup completed successfully', $results);
                
                return response()->json([
                    'success' => true,
                    'message' => 'System setup completed successfully!',
                    'steps' => $results['steps'],
                    'redirect' => route('dashboard'),
                ]);
            } else {
                Log::error('Production setup failed', $results);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Setup failed. Please check the errors below.',
                    'errors' => $results['errors'],
                    'steps' => $results['steps'],
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Setup controller error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during setup.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get setup progress
     */
    public function getProgress(): JsonResponse
    {
        try {
            $progress = $this->setupService->getSetupProgress();
            
            return response()->json([
                'success' => true,
                'progress' => $progress,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get setup progress: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get setup progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset setup (for development/testing purposes)
     */
    public function resetSetup(Request $request): JsonResponse
    {
        // Only allow in non-production environments
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Setup reset is not allowed in production environment',
            ], 403);
        }

        try {
            // Reset system configuration
            \App\Models\SystemConfiguration::where('key', 'system_configured')->delete();
            \App\Models\SystemConfiguration::where('key', 'database_migrated')->delete();
            \App\Models\SystemConfiguration::where('key', 'admin_created')->delete();
            \App\Models\SystemConfiguration::where('key', 'company_configured')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Setup has been reset successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset setup: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
