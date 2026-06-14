<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\UserController;

 

// Tambahkan ini di bagian route maintenance reports
Route::get('/maintenance/reports/{id}/export-word', [\App\Http\Controllers\MaintenanceController::class, 'exportWord']);

Route::get('/maintenance/reports/{id}/download-zip', [\App\Http\Controllers\MaintenanceController::class, 'downloadPhotosZip']);
// Tambahkan baris ini di dalam file routes/api.php Anda
Route::get('/user/achievements/{userId}', [\App\Http\Controllers\UserController::class, 'getAchievements']);

Route::get('/notifications', [App\Http\Controllers\MaintenanceController::class, 'getNotifications']);
Route::post('/notifications/{id}/read', [App\Http\Controllers\MaintenanceController::class, 'markAsRead']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
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
