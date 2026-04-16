<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; 
use Exception;

class AuthController extends Controller {
    
    // Sesuaikan path ini dengan hasil 'where python' di laptop Anda
    private $pythonPath = '"C:\Users\zacky\AppData\Local\Programs\Python\Python310\python.exe"';

    // --- LOGIN STANDAR KETIK ID ---
    public function login(Request $request) {
        try {
            $userIdInput = trim($request->user_id);
            $roleInput = strtolower(trim($request->role));

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

    // --- MENDAFTARKAN WAJAH KE DATABASE ---
    public function registerFingerprint(Request $request) {
        set_time_limit(180); 
        Log::info("Register Biometric Attempt untuk ID: " . $request->user_id);

        try {
            $request->validate([
                'user_id' => 'required',
                'fingerprint_image' => 'required|image'
            ]);

            $user = User::where('user_id', $request->user_id)->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User ID tidak terdaftar di sistem.']);
            }

            $enginePath = base_path('engine.py');

            // =====================================================================================
            // LOGIKA BARU: CEK APAKAH WAJAH SUDAH PERNAH DIDAFTARKAN KE ID INI
            // =====================================================================================
            if (!empty($user->biometric_hash)) {
                
                // Jika sudah ada, JANGAN DITIMPA. Lakukan pencocokan (Verifikasi)
                $tempPath = $request->file('fingerprint_image')->store('temp');
                $fullTempPath = storage_path('app/' . $tempPath);

                $hashPath = storage_path('app/temp_hash_register_check_' . uniqid() . '.txt');
                file_put_contents($hashPath, $user->biometric_hash);

                $command = $this->pythonPath . ' "' . $enginePath . '" match "' . $fullTempPath . '" "' . $hashPath . '" 2>&1';
                $matchResult = trim(shell_exec($command));

                Storage::delete($tempPath);
                if (file_exists($hashPath)) @unlink($hashPath);

                if ($matchResult === "100") {
                    // Wajah COCOK dengan yang terdaftar pertama kali (Biarkan lanjut login)
                    return response()->json(['success' => true, 'message' => 'Wajah dikenali. Melanjutkan...']);
                } else {
                    // Wajah BERBEDA, ini orang lain yang mencoba memakai ID yang sama
                    return response()->json(['success' => false, 'message' => 'Kredensial tidak valid'], 401);
                }
            }
            // =====================================================================================

            // JIKA BELUM ADA WAJAH (PENDAFTARAN PERTAMA), LANJUTKAN SIMPAN KE DATABASE
            // Simpan foto di storage/app/public/faces
            $namaFile = $user->user_id . '.jpg';
            $path = $request->file('fingerprint_image')->storeAs('faces', $namaFile, 'public');
            
            $fullPath = storage_path('app/public/' . $path);

            // 1. EKSTRAK 128 TITIK WAJAH MENGGUNAKAN PYTHON
            $command = $this->pythonPath . ' "' . $enginePath . '" register "' . $fullPath . '" 2>&1';
            $hashResult = trim(shell_exec($command));

            // 2. VALIDASI HASIL DARI PYTHON
            if (empty($hashResult) || $hashResult === "TIDAK_ADA_WAJAH" || strpos($hashResult, 'Traceback') !== false) {
                Log::error("Python AI Error: " . $hashResult);
                Storage::disk('public')->delete($path); 
                return response()->json(['success' => false, 'message' => 'AI Gagal mendeteksi wajah.']);
            }

            // 3. UPDATE DATABASE (Paksa simpan ke kolom biometric_hash)
            $updated = User::where('user_id', $user->user_id)->update([
                'biometric_hash' => $hashResult
            ]);

            if ($updated) {
                Log::info("MYSQL UPDATE SUCCESS: " . $user->user_id);
                return response()->json(['success' => true, 'message' => 'Wajah berhasil didaftarkan secara permanen!']);
            } else {
                return response()->json(['success' => false, 'message' => 'Gagal memperbarui data di database.']);
            }
            
        } catch (Exception $e) {
            Log::error("Register Biometric Exception: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()], 500);
        }
    }

    // --- LOGIN WAJAH (AUTO SCAN) ---
    public function loginFingerprint(Request $request) {
        try {
            $request->validate([
                'user_id' => 'required',
                'fingerprint_image' => 'required|image'
            ]);

            $user = User::where('user_id', $request->user_id)->first();
            if (!$user || empty($user->biometric_hash)) {
                return response()->json(['success' => false, 'message' => 'Anda belum mendaftarkan wajah.'], 404);
            }

            $path = $request->file('fingerprint_image')->store('temp');
            $fullPath = storage_path('app/' . $path);
            $enginePath = base_path('engine.py');

            $hashPath = storage_path('app/temp_hash_login_' . uniqid() . '.txt');
            file_put_contents($hashPath, $user->biometric_hash);

            $command = $this->pythonPath . ' "' . $enginePath . '" match "' . $fullPath . '" "' . $hashPath . '" 2>&1';
            $matchResult = trim(shell_exec($command));

            Storage::delete($path);
            if (file_exists($hashPath)) @unlink($hashPath);

            if ($matchResult === "100") {
                return response()->json(['success' => true, 'message' => 'Wajah Cocok!', 'user' => $user], 200);
            } else {
                return response()->json(['success' => false, 'message' => 'Wajah Tidak Dikenali.'], 401);
            }

        } catch (Exception $e) {
            Log::error("Login Biometric Exception: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }
}