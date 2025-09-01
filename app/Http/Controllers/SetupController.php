<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function setup()
    {
        $results = [];
        
        try {
            // 1. Check PHP version
            $results['php_version'] = [
                'current' => PHP_VERSION,
                'required' => '8.2.0',
                'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? '✅ OK' : '❌ TOO OLD'
            ];
            
            // 2. Check Laravel version
            $results['laravel_version'] = [
                'current' => app()->version(),
                'status' => '✅ OK'
            ];
            
            // 3. Check environment variables
            $results['env_variables'] = [
                'APP_MODE' => env('APP_MODE', 'NOT SET'),
                'SYNC_API_KEY' => env('SYNC_API_KEY', 'NOT SET'),
                'APP_URL' => env('APP_URL', 'NOT SET'),
                'DB_CONNECTION' => env('DB_CONNECTION', 'NOT SET')
            ];
            
            // 4. Check if required files exist
            $results['required_files'] = [
                'SyncController' => File::exists(app_path('Http/Controllers/Api/SyncController.php')) ? '✅ EXISTS' : '❌ MISSING',
                'EnsureSyncAuthorized' => File::exists(app_path('Http/Middleware/EnsureSyncAuthorized.php')) ? '✅ EXISTS' : '❌ MISSING',
                'SyncService' => File::exists(app_path('Services/SyncService.php')) ? '✅ EXISTS' : '❌ MISSING',
                'SyncLog Model' => File::exists(app_path('Models/SyncLog.php')) ? '✅ EXISTS' : '❌ MISSING'
            ];
            
            // 5. Check database tables
            $results['database_tables'] = [];
            try {
                // First check if we can connect to the database
                DB::connection()->getPdo();
                
                $tables = ['sync_logs', 'inventory', 'transactions', 'company_settings', 'users', 'inventory_adjustments'];
                foreach ($tables as $table) {
                    try {
                        $count = DB::table($table)->count();
                        $results['database_tables'][$table] = "✅ EXISTS ({$count} records)";
                    } catch (\Exception $e) {
                        $results['database_tables'][$table] = "❌ MISSING: " . $e->getMessage();
                    }
                }
            } catch (\Exception $e) {
                $results['database_tables']['error'] = "❌ DB ERROR: " . $e->getMessage();
                $results['database_tables']['connection'] = "❌ CANNOT CONNECT: " . $e->getMessage();
            }
            
            // 6. Check routes
            $results['routes'] = [];
            try {
                $routes = Route::getRoutes();
                $syncRoutes = [];
                foreach ($routes as $route) {
                    if (str_contains($route->uri(), 'sync')) {
                        $syncRoutes[] = [
                            'uri' => $route->uri(),
                            'methods' => $route->methods(),
                            'name' => $route->getName()
                        ];
                    }
                }
                $results['routes']['sync_routes'] = $syncRoutes;
                $results['routes']['total_sync_routes'] = count($syncRoutes);
            } catch (\Exception $e) {
                $results['routes']['error'] = "❌ ROUTE ERROR: " . $e->getMessage();
                $results['routes']['sync_routes'] = [];
                $results['routes']['total_sync_routes'] = 0;
            }
            
            // 7. Check configuration
            $results['configuration'] = [
                'app.mode' => config('app.mode'),
                'app.sync_api_key' => config('app.sync_api_key') ? 'SET' : 'NOT SET',
                'app.master_url' => config('app.master_url'),
                'app.timezone' => config('app.timezone')
            ];
            
            // 8. Check cache status
            $results['cache_status'] = [
                'config_cache' => File::exists(base_path('bootstrap/cache/config.php')) ? 'EXISTS' : 'CLEARED',
                'route_cache' => File::exists(base_path('bootstrap/cache/routes.php')) ? 'EXISTS' : 'CLEARED',
                'view_cache' => File::exists(base_path('bootstrap/cache/views.php')) ? 'EXISTS' : 'CLEARED'
            ];
            
            // 9. Check middleware registration
            $results['middleware'] = [];
            try {
                $middleware = app('router')->getMiddleware();
                $results['middleware']['sync_authorized'] = isset($middleware['sync.authorized']) ? '✅ REGISTERED' : '❌ NOT REGISTERED';
                $results['middleware']['total_middleware'] = count($middleware);
            } catch (\Exception $e) {
                $results['middleware']['error'] = "❌ MIDDLEWARE ERROR: " . $e->getMessage();
                $results['middleware']['sync_authorized'] = '❌ ERROR';
                $results['middleware']['total_middleware'] = 0;
            }
            
            // 10. Check if sync commands exist
            $results['commands'] = [];
            try {
                $commands = Artisan::all();
                $results['commands']['sync_run'] = isset($commands['sync:run']) ? '✅ EXISTS' : '❌ MISSING';
                $results['commands']['sync_company_settings'] = isset($commands['sync:company-settings']) ? '✅ EXISTS' : '❌ MISSING';
                $results['commands']['total_commands'] = count($commands);
            } catch (\Exception $e) {
                $results['commands']['error'] = "❌ COMMAND ERROR: " . $e->getMessage();
                $results['commands']['sync_run'] = '❌ ERROR';
                $results['commands']['sync_company_settings'] = '❌ ERROR';
                $results['commands']['total_commands'] = 0;
            }
            
            $results['status'] = 'success';
            $results['message'] = 'Setup check completed successfully';
            
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['message'] = 'Setup check failed: ' . $e->getMessage();
            $results['error_details'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        return view('setup.sync-setup', compact('results'));
    }
    
    public function test()
    {
        $results = [];
        
        try {
            // Test API endpoints
            $results['api_tests'] = [];
            
            // Test pull endpoint
            try {
                $response = app()->handle(
                    \Illuminate\Http\Request::create('/api/sync/pull?table=inventory&since=', 'GET')
                );
                $results['api_tests']['pull_endpoint'] = [
                    'status' => $response->getStatusCode(),
                    'accessible' => $response->getStatusCode() !== 404
                ];
            } catch (\Exception $e) {
                $results['api_tests']['pull_endpoint'] = [
                    'status' => 'ERROR',
                    'accessible' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Test push endpoint
            try {
                $response = app()->handle(
                    \Illuminate\Http\Request::create('/api/sync/push', 'POST')
                );
                $results['api_tests']['push_endpoint'] = [
                    'status' => $response->getStatusCode(),
                    'accessible' => $response->getStatusCode() !== 404
                ];
            } catch (\Exception $e) {
                $results['api_tests']['push_endpoint'] = [
                    'status' => 'ERROR',
                    'accessible' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Test with API key
            try {
                $request = \Illuminate\Http\Request::create('/api/sync/pull?table=inventory&since=', 'GET');
                $request->headers->set('X-Sync-API-Key', config('app.sync_api_key', 'test-key'));
                $response = app()->handle($request);
                $results['api_tests']['with_api_key'] = [
                    'status' => $response->getStatusCode(),
                    'authenticated' => $response->getStatusCode() !== 401
                ];
            } catch (\Exception $e) {
                $results['api_tests']['with_api_key'] = [
                    'status' => 'ERROR',
                    'authenticated' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $results['status'] = 'success';
            $results['message'] = 'API tests completed';
            
        } catch (\Exception $e) {
            $results['status'] = 'error';
            $results['message'] = 'API tests failed: ' . $e->getMessage();
        }
        
        return view('setup.sync-test', compact('results'));
    }
    
    public function clearCache()
    {
        try {
            $results = [];
            
            // Clear config cache
            try {
                if (File::exists(base_path('bootstrap/cache/config.php'))) {
                    File::delete(base_path('bootstrap/cache/config.php'));
                    $results['config_cache'] = '✅ CLEARED';
                } else {
                    $results['config_cache'] = '✅ ALREADY CLEARED';
                }
            } catch (\Exception $e) {
                $results['config_cache'] = '❌ ERROR: ' . $e->getMessage();
            }
            
            // Clear route cache
            try {
                if (File::exists(base_path('bootstrap/cache/routes.php'))) {
                    File::delete(base_path('bootstrap/cache/routes.php'));
                    $results['route_cache'] = '✅ CLEARED';
                } else {
                    $results['route_cache'] = '✅ ALREADY CLEARED';
                }
            } catch (\Exception $e) {
                $results['route_cache'] = '❌ ERROR: ' . $e->getMessage();
            }
            
            // Clear view cache
            try {
                if (File::exists(base_path('bootstrap/cache/views.php'))) {
                    File::delete(base_path('bootstrap/cache/views.php'));
                    $results['view_cache'] = '✅ CLEARED';
                } else {
                    $results['view_cache'] = '✅ ALREADY CLEARED';
                }
            } catch (\Exception $e) {
                $results['view_cache'] = '❌ ERROR: ' . $e->getMessage();
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Cache cleared successfully',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function remove()
    {
        try {
            // Remove setup routes from web.php
            $webPath = base_path('routes/web.php');
            $content = File::get($webPath);
            
            // Remove the setup routes
            $content = preg_replace('/\/\/ TEMPORARY SETUP ROUTE.*?Route::get\(\'\/remove-setup.*?\n\n/s', '', $content);
            
            File::put($webPath, $content);
            
            // Delete the setup controller
            File::delete(app_path('Http/Controllers/SetupController.php'));
            
            // Delete setup views
            File::deleteDirectory(resource_path('views/setup'));
            
            return response()->json([
                'status' => 'success',
                'message' => 'Setup routes and files removed successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove setup: ' . $e->getMessage()
            ], 500);
        }
    }
}
