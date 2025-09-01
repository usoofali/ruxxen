<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Module Setup - Ruxxen Gas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-ok { color: #059669; }
        .status-error { color: #dc2626; }
        .status-warning { color: #d97706; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🔄 Sync Module Setup</h1>
                <p class="text-gray-600">Comprehensive configuration and status check for the Master-Slave sync module</p>
                
                <div class="mt-4 flex gap-4">
                    <a href="/test-sync" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        🧪 Test API Endpoints
                    </a>
                    <a href="/clear-cache" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg" 
                       onclick="return confirm('Are you sure you want to clear all caches?')">
                        🗂️ Clear Caches
                    </a>
                    <a href="/remove-setup" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg" 
                       onclick="return confirm('Are you sure you want to remove the setup routes?')">
                        🗑️ Remove Setup Routes
                    </a>
                </div>
            </div>

            <!-- PHP Version Check -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🐘 PHP Version Check</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">Current Version:</span>
                        <div class="text-lg font-mono">{{ $results['php_version']['current'] }}</div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">Required Version:</span>
                        <div class="text-lg font-mono">{{ $results['php_version']['required'] }}</div>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">Status:</span>
                        <div class="text-lg font-semibold {{ str_contains($results['php_version']['status'], 'OK') ? 'status-ok' : 'status-error' }}">
                            {{ $results['php_version']['status'] }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Laravel Version -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🚀 Laravel Version</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <span class="text-sm text-gray-600">Current Version:</span>
                    <div class="text-lg font-mono">{{ $results['laravel_version']['current'] }}</div>
                    <div class="text-sm text-green-600 mt-1">{{ $results['laravel_version']['status'] }}</div>
                </div>
            </div>

            <!-- Environment Variables -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">⚙️ Environment Variables</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($results['env_variables'] as $key => $value)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ $key }}:</span>
                        <div class="text-lg font-mono break-all">
                            @if($key === 'SYNC_API_KEY' && $value !== 'NOT SET')
                                {{ str_repeat('*', 8) }}
                            @else
                                {{ $value }}
                            @endif
                        </div>
                        <div class="text-sm {{ $value === 'NOT SET' ? 'status-error' : 'status-ok' }} mt-1">
                            {{ $value === 'NOT SET' ? '❌ NOT SET' : '✅ SET' }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Required Files -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">📁 Required Files</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($results['required_files'] as $file => $status)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ $file }}:</span>
                        <div class="text-lg font-semibold {{ str_contains($status, 'EXISTS') ? 'status-ok' : 'status-error' }}">
                            {{ $status }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Database Tables -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🗄️ Database Tables</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($results['database_tables'] as $table => $status)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ $table }}:</span>
                        <div class="text-lg font-semibold {{ str_contains($status, 'EXISTS') ? 'status-ok' : 'status-error' }}">
                            {{ $status }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Routes -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🛣️ Sync Routes</h2>
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <span class="text-sm text-gray-600">Total Sync Routes Found:</span>
                    <div class="text-2xl font-bold text-blue-600">{{ $results['routes']['total_sync_routes'] ?? 0 }}</div>
                </div>
                
                @if(isset($results['routes']['sync_routes']) && count($results['routes']['sync_routes']) > 0)
                <div class="space-y-2">
                    @foreach($results['routes']['sync_routes'] as $route)
                    <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                        <div class="font-mono text-sm">
                            <span class="text-blue-600 font-semibold">{{ implode('|', $route['methods']) }}</span>
                            <span class="text-gray-800">{{ $route['uri'] }}</span>
                            @if($route['name'])
                                <span class="text-gray-500">({{ $route['name'] }})</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                    <div class="text-red-800">❌ No sync routes found!</div>
                </div>
                @endif
            </div>

            <!-- Configuration -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">⚙️ Configuration</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($results['configuration'] as $key => $value)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ $key }}:</span>
                        <div class="text-lg font-mono break-all">
                            @if($key === 'app.sync_api_key' && $value === 'SET')
                                {{ str_repeat('*', 8) }}
                            @else
                                {{ $value ?? 'NULL' }}
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Cache Status -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🗂️ Cache Status</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($results['cache_status'] as $cache => $status)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $cache)) }}:</span>
                        <div class="text-lg font-semibold {{ $status === 'CLEARED' ? 'status-ok' : 'status-warning' }}">
                            {{ $status }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Middleware -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🛡️ Middleware</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($results['middleware'] as $key => $status)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                        <div class="text-lg font-semibold {{ str_contains($status, 'REGISTERED') ? 'status-ok' : 'status-error' }}">
                            {{ $status }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Commands -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">⚡ Artisan Commands</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($results['commands'] as $command => $status)
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm text-gray-600">{{ $command }}:</span>
                        <div class="text-lg font-semibold {{ str_contains($status, 'EXISTS') ? 'status-ok' : 'status-error' }}">
                            {{ $status }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Summary</h2>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-lg font-semibold {{ $results['status'] === 'success' ? 'status-ok' : 'status-error' }}">
                        {{ $results['message'] }}
                    </div>
                    
                    @if(isset($results['error_details']))
                    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <h3 class="font-semibold text-red-800 mb-2">Error Details:</h3>
                        <div class="text-sm text-red-700">
                            <div><strong>File:</strong> {{ $results['error_details']['file'] }}</div>
                            <div><strong>Line:</strong> {{ $results['error_details']['line'] }}</div>
                            <div><strong>Trace:</strong></div>
                            <pre class="mt-2 text-xs overflow-x-auto">{{ $results['error_details']['trace'] }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">📋 Next Steps</h3>
                <div class="text-blue-700 space-y-2">
                    <div>1. <strong>Fix PHP Version:</strong> Upgrade to PHP 8.2+ if not already done</div>
                    <div>2. <strong>Set Environment Variables:</strong> Ensure APP_MODE=master and SYNC_API_KEY are set</div>
                    <div>3. <strong>Clear Caches:</strong> Run cache clearing commands if possible</div>
                    <div>4. <strong>Test API Endpoints:</strong> Use the "Test API Endpoints" button above</div>
                    <div>5. <strong>Remove Setup Routes:</strong> Use the "Remove Setup Routes" button when done</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
