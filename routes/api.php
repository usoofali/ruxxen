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

// Sync API Routes
Route::prefix('sync')->group(function () {
    Route::post('/upload', [SyncController::class, 'upload'])->name('api.sync.upload');
    Route::get('/download', [SyncController::class, 'download'])->name('api.sync.download');
    Route::post('/acknowledge', [SyncController::class, 'acknowledge'])->name('api.sync.acknowledge');
});

// Homepage API Route
