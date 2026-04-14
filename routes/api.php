<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenanceController;





Route::post('/maintenance/report/{id}/add-photos', [App\Http\Controllers\MaintenanceController::class, 'addPhotos']);
// Pastikan nama Class-nya adalah MaintenanceController
Route::put('/maintenance/reports/{id}/status', [MaintenanceController::class, 'updateStatus']);
Route::post('/register-fingerprint', [AuthController::class, 'registerFingerprint']);
Route::post('/login-fingerprint', [AuthController::class, 'loginFingerprint']);
Route::post('/users/register', [App\Http\Controllers\AuthController::class, 'register']);
Route::put('/maintenance/reports/{id}', [App\Http\Controllers\MaintenanceController::class, 'updateData']);
Route::put('/user/update/{id}', [AuthController::class, 'update']);
Route::get('/maintenance/reports', [MaintenanceController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/maintenance/report', [MaintenanceController::class, 'store']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
