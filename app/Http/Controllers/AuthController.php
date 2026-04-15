<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; 
use Exception;

class AuthController extends Controller {
    
    // --- LOGIN STANDAR KETIK ID ---
    public function login(Request $request) {
        try {
            $userIdInput = trim($request->user_id);
            $roleInput = strtolower(trim($request->role));

            // Mapping role agar sesuai dengan yang ada di Database
            $roleDb = (strpos($roleInput, 'admin') !== false || strpos($roleInput, 'administrasi') !== false) 
                      ? 'Tim Administrasi' : 'Tim Lapangan'; 

            $user = User::where('user_id', $userIdInput)->first();

            if (!$user || $user->role !== $roleDb) {
                return response()->json(['success' => false, 'message' => "Login Gagal. Cek ID dan Role."], 401);
            }

            return response()->json(['success' => true, 'message' => 'Login Berhasil', 'user' => $user], 200);
        } catch (Exception $e) {
            Log::error("Login Manual Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    // --- REGISTER USER BARU ---
    public function register(Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|string|max:50|unique:users', 
            'role' => 'required|in:Tim Lapangan,Tim Administrasi',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'user_id' => $request->user_id,
                'role' => $request->role,
            ]);

            return response()->json(['success' => true, 'message' => 'Berhasil daftar!', 'user' => $user], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mendaftarkan user.'], 500);
        }
    }

    // --- MENDAFTARKAN ATAU MEMPERBARUI WAJAH ---
    public function registerFingerprint(Request $request) {
        set_time_limit(180); // Naikkan limit waktu karena AI butuh proses

        try {
            $request->validate([
                'user_id' => 'required',
                'fingerprint_image' => 'required|image'
            ]);

            $user = User::where('user_id', $request->user_id)->first();
            if (!$user) return response()->json(['success' => false, 'message' => 'User tidak ditemukan.']);

            // Simpan foto sementara
            $path = $request->file('fingerprint_image')->store('temp');
            $fullPath = storage_path('app/' . $path);
            $enginePath = base_path('engine.py');

            // 1. VERIFIKASI PEMILIK ASLI (Jika sudah ada data wajah sebelumnya)
            if (!empty($user->biometric_hash)) {
                $hashPath = storage_path('app/temp_hash_verif_' . uniqid() . '.txt');
                file_put_contents($hashPath, $user->biometric_hash);

                // Gunakan escapeshellarg untuk keamanan path
                $matchCommand = "python3 " . escapeshellarg($enginePath) . " match " . escapeshellarg($fullPath) . " " . escapeshellarg($hashPath) . " 2>&1";
                $matchResult = trim(shell_exec($matchCommand));
                @unlink($hashPath);

                if ($matchResult !== "100") {
                    Storage::delete($path);
                    return response()->json([
                        'success' => false, 
                        'message' => 'ID Terkunci! Wajah Anda tidak cocok dengan pemilik asli ID ini.'
                    ], 403);
                }
            }

            // 2. EKSTRAK WAJAH BARU (Register)
            $command = "python " . escapeshellarg($enginePath) . " register " . escapeshellarg($fullPath) . " 2>&1";
            $hashResult = trim(shell_exec($command));
            
            // Hapus file foto asli segera setelah diproses
            Storage::delete($path);

            // Validasi hasil AI
            if (empty($hashResult) || $hashResult === "TIDAK_ADA_WAJAH" || strpos($hashResult, 'Traceback') !== false) {
                Log::error("Python Register Error: " . $hashResult);
                return response()->json([
                    'success' => false, 
                    'message' => 'AI Gagal mendeteksi wajah. Pastikan cahaya terang dan wajah terlihat jelas.'
                ]);
            }

            // Simpan ke database
            $user->biometric_hash = $hashResult;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Data Wajah BERHASIL diamankan!']);
            
        } catch (Exception $e) {
            Log::error("Register Biometric Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()], 500);
        }
    }

    // --- LOGIN MENGGUNAKAN WAJAH (AUTO SCAN) ---
    public function loginFingerprint(Request $request) {
        try {
            $request->validate([
                'user_id' => 'required',
                'fingerprint_image' => 'required|image'
            ]);

            $user = User::where('user_id', $request->user_id)->first();
            if (!$user || empty($user->biometric_hash)) {
                return response()->json(['success' => false, 'message' => 'Akun belum memiliki data Wajah.'], 404);
            }

            $path = $request->file('fingerprint_image')->store('temp');
            $fullPath = storage_path('app/' . $path);
            $enginePath = base_path('engine.py');

            // Simpan Hash DB ke file text sementara untuk dibandingkan oleh Python
            $hashPath = storage_path('app/temp_hash_login_' . uniqid() . '.txt');
            file_put_contents($hashPath, $user->biometric_hash);

            // Eksekusi Python dengan escapeshellarg
            $command = "python " . escapeshellarg($enginePath) . " match " . escapeshellarg($fullPath) . " " . escapeshellarg($hashPath) . " 2>&1";
            $matchResult = trim(shell_exec($command));

            // Bersihkan file sementara
            Storage::delete($path);
            if (file_exists($hashPath)) @unlink($hashPath);

            if ($matchResult === "100") {
                return response()->json(['success' => true, 'message' => 'Wajah Cocok!', 'user' => $user], 200);
            } elseif ($matchResult === "0") {
                return response()->json(['success' => false, 'message' => 'Wajah Tidak Dikenali.'], 401);
            } else {
                Log::error("Python Match Error Result: " . $matchResult);
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal memproses pengenalan wajah.',
                    'debug' => substr($matchResult, 0, 50)
                ], 500);
            }

        } catch (Exception $e) {
            Log::error("Login Biometric Exception: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }
}