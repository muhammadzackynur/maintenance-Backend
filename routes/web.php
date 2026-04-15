<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

Route::get('/storage/reports/{filename}', function ($filename) {
    // Cari alamat asli file-nya
    $path = storage_path('app/public/reports/' . $filename);
    
    // Jika tidak ada, kembalikan error 404
    if (!File::exists($path)) {
        abort(404);
    }
    
    // Jika ada, kirimkan file-nya sebagai gambar
    $file = File::get($path);
    $type = File::mimeType($path);
    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);
    
    return $response;
});
