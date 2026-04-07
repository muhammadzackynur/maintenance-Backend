<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller {
    
    // --- LOGIN STANDAR KETIK ID ---
    public function login(Request $request) {
        $userIdInput = trim($request->user_id);
        $roleInput = strtolower(trim($request->role));

        $roleDb = (strpos($roleInput, 'admin') !== false || strpos($roleInput, 'administrasi') !== false) 
                  ? 'Tim Administrasi' : 'Tim Lapangan'; 

        $user = User::where('user_id', $userIdInput)->first();

        if (!$user || $user->role !== $roleDb) {
            return response()->json(['success' => false, 'message' => "Login Gagal. Cek ID dan Role."], 401);
        }

        return response()->json(['success' => true, 'message' => 'Login Berhasil', 'user' => $user], 200);
    }

    public function register(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|string|max:50|unique:users', 
            'role' => 'required|in:Tim Lapangan,Tim Administrasi',
        ]);

        $user = User::create([
            'name' => $request->name,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        return response()->json(['success' => true, 'message' => 'Berhasil daftar!', 'user' => $user], 201);
    }

    // --- MENDAFTARKAN SIDIK JARI VIA KAMERA ---
    public function registerFingerprint(Request $request) {
        $request->validate([
            'user_id' => 'required',
            'fingerprint_image' => 'required|image'
        ]);

        $user = User::where('user_id', $request->user_id)->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'User tidak ditemukan']);

        // Simpan foto sementara
        $path = $request->file('fingerprint_image')->store('temp');
        $fullPath = storage_path('app/' . $path);

        // Panggil Python untuk mengubah gambar jadi teks geometrik
        $command = escapeshellcmd("python " . base_path('engine.py') . " register " . escapeshellarg($fullPath));
        $hashResult = trim(shell_exec($command));

        Storage::delete($path); // Hapus foto asli

        if ($hashResult == "ERROR" || empty($hashResult)) {
            return response()->json(['success' => false, 'message' => 'Gagal membaca garis jari. Coba lagi di tempat terang.']);
        }

        $user->biometric_hash = $hashResult;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Sidik jari berhasil disimpan ke database!']);
    }

    // --- LOGIN MENGGUNAKAN SIDIK JARI KAMERA ---
    public function loginFingerprint(Request $request) {
        $request->validate([
            'user_id' => 'required',
            'fingerprint_image' => 'required|image'
        ]);

        $user = User::where('user_id', $request->user_id)->first();
        if (!$user || empty($user->biometric_hash)) {
            return response()->json(['success' => false, 'message' => 'Akun belum memiliki data sidik jari.']);
        }

        $path = $request->file('fingerprint_image')->store('temp');
        $fullPath = storage_path('app/' . $path);

        // Panggil Python membandingkan gambar baru dgn Hash di Database
        $savedHash = escapeshellarg($user->biometric_hash);
        $command = escapeshellcmd("python " . base_path('engine.py') . " match " . escapeshellarg($fullPath) . " " . $savedHash);
        $matchResult = trim(shell_exec($command));

        Storage::delete($path); // Hapus foto

        if ($matchResult === "100") {
            return response()->json(['success' => true, 'message' => 'Sidik Jari Cocok!', 'user' => $user], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Login ditolak! Sidik Jari Tidak Dikenali.'], 401);
        }
    }
}