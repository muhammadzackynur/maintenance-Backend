<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\UserController;

// Route untuk History dan Assign Teknisi (BARU)
Route::put('/maintenance/reports/{id}/assign', [MaintenanceController::class, 'assignTechnicians']);
Route::get('/maintenance/history/{userId}', [MaintenanceController::class, 'getHistory']);

// Route Ekspor & Unduh Foto
Route::get('/maintenance/reports/{id}/export-word', [MaintenanceController::class, 'exportWord']);
Route::get('/maintenance/reports/{id}/download-zip', [MaintenanceController::class, 'downloadPhotosZip']);

// Route User & Notifications
Route::get('/user/achievements/{userId}', [UserController::class, 'getAchievements']);
Route::get('/notifications', [MaintenanceController::class, 'getNotifications']);
Route::post('/notifications/{id}/read', [MaintenanceController::class, 'markAsRead']);
Route::get('/users', [UserController::class, 'index']);
Route::put('/user/update/{id}', [AuthController::class, 'update']);

// Route Maintenance Laporan
Route::post('/maintenance/report/{id}/add-photos', [MaintenanceController::class, 'addPhotos']);
Route::put('/maintenance/reports/{id}/status', [MaintenanceController::class, 'updateStatus']);
Route::put('/maintenance/reports/{id}', [MaintenanceController::class, 'updateData']);
Route::get('/maintenance/reports', [MaintenanceController::class, 'index']);
Route::post('/maintenance/report', [MaintenanceController::class, 'store']);

// Route Auth & Login (Public / Tidak butuh token)
Route::post('/register-fingerprint', [AuthController::class, 'registerFingerprint']);
Route::post('/login-fingerprint', [AuthController::class, 'loginFingerprint']);
Route::post('/users/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// PERBAIKAN: Gunakan Route::group() untuk rute-rute yang wajib login (Protected Routes)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // =========================================================================
    // SUDAH DIPERBAIKI: Sekarang bisa menerima /user/update-photo/1028
    // =========================================================================
    Route::post('/user/update-photo/{id?}', [UserController::class, 'updatePhoto']);
    
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});