<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;

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

// Sync API routes
Route::prefix('sync')->group(function () {
    Route::get('tables', [SyncController::class, 'tables']);
    Route::get('status', [SyncController::class, 'status']);
    Route::get('status/{tableName}', [SyncController::class, 'tableStatus']);
    Route::get('pull/{tableName}', [SyncController::class, 'pull']);
    Route::post('push/{tableName}', [SyncController::class, 'push']);
    Route::post('reset/{tableName?}', [SyncController::class, 'reset']);
    Route::post('full', [SyncController::class, 'fullSync']);
    
    // Additional sync methods for compatibility
    Route::post('upload', [SyncController::class, 'upload']);
    Route::get('download', [SyncController::class, 'download']);
    Route::post('acknowledge', [SyncController::class, 'acknowledge']);
});