<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync API Testing - Ruxxen Gas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .status-ok { color: #059669; }
        .status-error { color: #dc2626; }
        .status-warning { color: #d97706; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🧪 Sync API Testing</h1>
                <p class="text-gray-600">Testing the Master sync API endpoints for accessibility and authentication</p>
                
                <div class="mt-4 flex gap-4">
                    <a href="/setup-sync" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        ← Back to Setup
                    </a>
                    <a href="/remove-setup" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg" 
                       onclick="return confirm('Are you sure you want to remove the setup routes?')">
                        🗑️ Remove Setup Routes
                    </a>
                </div>
            </div>

            <!-- Test Results -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 API Test Results</h2>
                
                @if($results['status'] === 'success')
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <div class="text-green-800 font-semibold">✅ {{ $results['message'] }}</div>
                    </div>
                @else
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <div class="text-red-800 font-semibold">❌ {{ $results['message'] }}</div>
                    </div>
                @endif

                <!-- Pull Endpoint Test -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">GET /api/sync/pull</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-600">Status Code:</span>
                                <div class="text-lg font-mono font-semibold 
                                    {{ $results['api_tests']['pull_endpoint']['status'] === 200 ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['pull_endpoint']['status'] }}
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Accessible:</span>
                                <div class="text-lg font-semibold 
                                    {{ $results['api_tests']['pull_endpoint']['accessible'] ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['pull_endpoint']['accessible'] ? '✅ YES' : '❌ NO' }}
                                </div>
                            </div>
                        </div>
                        @if(isset($results['api_tests']['pull_endpoint']['error']))
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded">
                            <div class="text-sm text-red-700">
                                <strong>Error:</strong> {{ $results['api_tests']['pull_endpoint']['error'] }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Push Endpoint Test -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">POST /api/sync/push</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-600">Status Code:</span>
                                <div class="text-lg font-mono font-semibold 
                                    {{ $results['api_tests']['push_endpoint']['status'] === 200 ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['push_endpoint']['status'] }}
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Accessible:</span>
                                <div class="text-lg font-semibold 
                                    {{ $results['api_tests']['push_endpoint']['accessible'] ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['push_endpoint']['accessible'] ? '✅ YES' : '❌ NO' }}
                                </div>
                            </div>
                        </div>
                        @if(isset($results['api_tests']['push_endpoint']['error']))
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded">
                            <div class="text-sm text-red-700">
                                <strong>Error:</strong> {{ $results['api_tests']['push_endpoint']['error'] }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- API Key Authentication Test -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">🔑 API Key Authentication</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm text-gray-600">Status Code:</span>
                                <div class="text-lg font-mono font-semibold 
                                    {{ $results['api_tests']['with_api_key']['status'] === 200 ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['with_api_key']['status'] }}
                                </div>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Authenticated:</span>
                                <div class="text-lg font-semibold 
                                    {{ $results['api_tests']['with_api_key']['authenticated'] ? 'status-ok' : 'status-error' }}">
                                    {{ $results['api_tests']['with_api_key']['authenticated'] ? '✅ YES' : '❌ NO' }}
                                </div>
                            </div>
                        </div>
                        @if(isset($results['api_tests']['with_api_key']['error']))
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded">
                            <div class="text-sm text-red-700">
                                <strong>Error:</strong> {{ $results['api_tests']['with_api_key']['error'] }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Test Summary -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">📋 Test Summary</h2>
                
                @php
                    $totalTests = count($results['api_tests']);
                    $passedTests = 0;
                    foreach($results['api_tests'] as $test) {
                        if(isset($test['accessible']) && $test['accessible']) $passedTests++;
                        if(isset($test['authenticated']) && $test['authenticated']) $passedTests++;
                    }
                    $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
                @endphp
                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <span class="text-sm text-gray-600">Total Tests:</span>
                            <div class="text-2xl font-bold text-blue-600">{{ $totalTests }}</div>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Passed Tests:</span>
                            <div class="text-2xl font-bold text-green-600">{{ $passedTests }}</div>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Success Rate:</span>
                            <div class="text-2xl font-bold {{ $successRate >= 80 ? 'text-green-600' : ($successRate >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $successRate }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Testing Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">🔧 Manual Testing Instructions</h3>
                <div class="text-blue-700 space-y-3">
                    <div>
                        <strong>1. Test Pull Endpoint:</strong>
                        <div class="font-mono text-sm mt-1 bg-white p-2 rounded">
                            curl -H "X-Sync-API-Key: YOUR_API_KEY" "{{ url('/api/sync/pull?table=inventory&since=') }}"
                        </div>
                    </div>
                    
                    <div>
                        <strong>2. Test Push Endpoint:</strong>
                        <div class="font-mono text-sm mt-1 bg-white p-2 rounded">
                            curl -X POST -H "X-Sync-API-Key: YOUR_API_KEY" -H "Content-Type: application/json" -d '{"table":"inventory","data":[]}' "{{ url('/api/sync/push') }}"
                        </div>
                    </div>
                    
                    <div>
                        <strong>3. Test Without API Key (Should Return 401):</strong>
                        <div class="font-mono text-sm mt-1 bg-white p-2 rounded">
                            curl "{{ url('/api/sync/pull?table=inventory&since=') }}"
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mt-6">
                <h3 class="text-lg font-semibold text-yellow-800 mb-3">⚠️ Troubleshooting</h3>
                <div class="text-yellow-700 space-y-2">
                    <div><strong>404 Errors:</strong> Routes not registered or cache not cleared</div>
                    <div><strong>500 Errors:</strong> PHP version too old or missing dependencies</div>
                    <div><strong>401 Errors:</strong> API key not set or middleware not working</div>
                    <div><strong>No Routes Found:</strong> Check if APP_MODE=master in .env</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
