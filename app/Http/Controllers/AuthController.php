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

    // --- REGISTER USER BARU ---
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

    // --- MENDAFTARKAN ATAU MEMPERBARUI WAJAH ---
    public function registerFingerprint(Request $request) {
        set_time_limit(120); 

        $request->validate([
            'user_id' => 'required',
            'fingerprint_image' => 'required|image'
        ]);

        $user = User::where('user_id', $request->user_id)->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'User tidak ditemukan di sistem.']);

        $path = $request->file('fingerprint_image')->store('temp');
        $fullPath = storage_path('app/' . $path);

        // ================================================================
        // CEK JIKA WAJAH SUDAH ADA: LAKUKAN VERIFIKASI SEBELUM MENIMPA
        // ================================================================
        if (!empty($user->biometric_hash)) {
            
            // Simpan hash lama ke file sementara untuk dicocokkan
            $hashPath = storage_path('app/temp_hash_verif_' . uniqid() . '.txt');
            file_put_contents($hashPath, $user->biometric_hash);

            // Panggil AI untuk mencocokkan wajah baru dengan wajah lama
            $matchCommand = "python " . escapeshellarg(base_path('engine.py')) . " match " . escapeshellarg($fullPath) . " " . escapeshellarg($hashPath) . " 2>&1";
            $matchResult = trim(shell_exec($matchCommand));

            @unlink($hashPath); // Hapus file hash sementara

            // Jika yang mendaftar BUKAN pemilik asli, TOLAK MENTAH-MENTAH
            if ($matchResult !== "100") {
                Storage::delete($path); // Hapus foto penyusup
                return response()->json([
                    'success' => false, 
                    'message' => 'GAGAL: ID ini sudah dikunci! Wajah Anda TIDAK COCOK dengan pemilik asli ID ini.'
                ]);
            }
            // Jika hasilnya "100" (Pemilik Asli), proses akan dibiarkan lanjut ke bawah untuk menimpa data
        }

        // ================================================================
        // PROSES EKSTRAK WAJAH BARU (JIKA BARU PERTAMA KALI ATAU LOLOS VERIFIKASI)
        // ================================================================
        $command = "python " . escapeshellarg(base_path('engine.py')) . " register " . escapeshellarg($fullPath) . " 2>&1";
        $hashResult = trim(shell_exec($command));

        Storage::delete($path); // Hapus foto asli

        // Blokir jika AI gagal melihat wajah
        if ($hashResult === "TIDAK_ADA_WAJAH" || $hashResult === "ERROR_NO_FACE" || empty($hashResult)) {
            return response()->json([
                'success' => false, 
                'message' => 'GAGAL: AI tidak menemukan wajah! Pastikan Anda menghadap cahaya terang.'
            ]);
        }

        if (strpos(strtolower($hashResult), 'error') !== false || strpos(strtolower($hashResult), 'traceback') !== false) {
            return response()->json(['success' => false, 'message' => 'AI Crash: ' . substr($hashResult, 0, 80)]);
        }

        // Simpan Data Wajah Baru ke Database (Otomatis menimpa yang lama)
        $user->biometric_hash = $hashResult;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Data Wajah BERHASIL diperbarui & diamankan!']);
    }

    // --- LOGIN MENGGUNAKAN WAJAH (AUTO SCAN) ---
    // --- LOGIN MENGGUNAKAN WAJAH (AUTO SCAN) ---
    public function loginFingerprint(Request $request) {
        $request->validate([
            'user_id' => 'required',
            'fingerprint_image' => 'required|image'
        ]);

        $user = User::where('user_id', $request->user_id)->first();
        if (!$user || empty($user->biometric_hash)) {
            return response()->json(['success' => false, 'message' => 'Akun belum memiliki data Wajah. Silakan login manual dan daftarkan wajah.']);
        }

        $path = $request->file('fingerprint_image')->store('temp');
        $fullPath = storage_path('app/' . $path);

        // --- PERBAIKAN: Simpan Hash ke File Text sementara ---
        $hashPath = storage_path('app/temp_hash_' . uniqid() . '.txt');
        file_put_contents($hashPath, $user->biometric_hash);

        // --- PERBAIKAN: Gunakan escapeshellarg dgn benar & tambahkan 2>&1 ---
        // 2>&1 berfungsi agar jika python error, errornya tidak mematikan server PHP
        $command = "python " . escapeshellarg(base_path('engine.py')) . " match " . escapeshellarg($fullPath) . " " . escapeshellarg($hashPath) . " 2>&1";
        $matchResult = trim(shell_exec($command));

        Storage::delete($path); // Hapus foto dari storage
        @unlink($hashPath);     // Hapus file text hash sementara

        if ($matchResult === "100") {
            return response()->json(['success' => true, 'message' => 'Wajah Cocok! Login Berhasil.', 'user' => $user], 200);
        } else {
            // Jika hasil bukan 100 dan bukan 0 (berarti script Python error), kembalikan pesan errornya
            if ($matchResult !== "0" && !empty($matchResult)) {
                return response()->json(['success' => false, 'message' => 'Backend Python Error: ' . substr($matchResult, 0, 50)], 401);
            }
            return response()->json(['success' => false, 'message' => 'Login Ditolak! Wajah Tidak Dikenali.'], 401);
        }
    }
}