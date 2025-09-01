<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Sync API routes (only available in master mode)
if (config('app.mode') === 'master') {
    Route::middleware('sync.authorized')->group(function () {
        Route::post('/sync/push', [App\Http\Controllers\Api\SyncController::class, 'push']);
        Route::get('/sync/pull', [App\Http\Controllers\Api\SyncController::class, 'pull']);
    });
}
