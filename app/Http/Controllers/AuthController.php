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
        set_time_limit(180); 

        try {
            $request->validate([
                'user_id' => 'required',
                'fingerprint_image' => 'required|image'
            ]);

            $user = User::where('user_id', $request->user_id)->first();
            if (!$user) return response()->json(['success' => false, 'message' => 'User tidak ditemukan.']);

            // --- TRIK BARU: SIMPAN LANGSUNG KE FOLDER PERMANEN ---
            // Kita tidak pakai folder temp lagi, langsung amankan ke public/faces
            $namaFile = $user->user_id . '.jpg';
            $path = $request->file('fingerprint_image')->storeAs('faces', $namaFile, 'public');
            
            // Path lengkap untuk dibaca oleh Python
            $fullPath = storage_path('app/public/' . $path);
            $enginePath = base_path('engine.py');

            // 1. VERIFIKASI PEMILIK ASLI (Jika sudah ada data wajah di DB sebelumnya)
            if (!empty($user->biometric_hash)) {
                $hashPath = storage_path('app/temp_hash_verif_' . uniqid() . '.txt');
                file_put_contents($hashPath, $user->biometric_hash);

                $matchCommand = 'python "' . $enginePath . '" match "' . $fullPath . '" "' . $hashPath . '" 2>&1';
                $matchResult = trim(shell_exec($matchCommand));
                @unlink($hashPath);

                if ($matchResult !== "100") {
                    // Karena ini bukan pemilik asli, hapus foto yang baru saja numpang masuk
                    Storage::disk('public')->delete($path); 
                    return response()->json([
                        'success' => false, 
                        'message' => 'ID Terkunci! Wajah Anda tidak cocok dengan pemilik asli ID ini.'
                    ], 403);
                }
            }

            // 2. EKSTRAK WAJAH BARU (Register)
            $command = 'python "' . $enginePath . '" register "' . $fullPath . '" 2>&1';
            $hashResult = trim(shell_exec($command));

            // 3. VALIDASI HASIL AI
            if (empty($hashResult) || $hashResult === "TIDAK_ADA_WAJAH" || strpos($hashResult, 'Traceback') !== false) {
                Log::error("Python Register Error: " . $hashResult);
                
                // Jika AI gagal baca muka, hapus foto tersebut
                Storage::disk('public')->delete($path); 
                return response()->json([
                    'success' => false, 
                    'message' => 'AI Gagal mendeteksi wajah. Pastikan cahaya terang dan wajah terlihat jelas.'
                ]);
            }

            // 4. SIMPAN KE DATABASE
            // Karena fotonya sudah ada di public/faces, kita tinggal menyimpan Sandi AI ke Database.
            $user->biometric_hash = $hashResult;
            $user->save();

            return response()->json(['success' => true, 'message' => 'Data Wajah & Foto BERHASIL disimpan permanen di Storage!']);
            
        } catch (Exception $e) {
            Log::error("Register Biometric Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error Server: ' . $e->getMessage()], 500);
        }
    }

    // --- LOGIN MENGGUNAKAN WAJAH (AUTO SCAN) ---
    // Di sini kita tetap pakai file temp, agar foto absen login sehari-hari tidak memenuhi disk komputer.
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

            // Foto jepretan saat login (sementara)
            $path = $request->file('fingerprint_image')->store('temp');
            $fullPath = storage_path('app/' . $path);
            $enginePath = base_path('engine.py');

            // Simpan Hash DB ke file text sementara untuk dibandingkan oleh Python
            $hashPath = storage_path('app/temp_hash_login_' . uniqid() . '.txt');
            file_put_contents($hashPath, $user->biometric_hash);

            $command = 'python "' . $enginePath . '" match "' . $fullPath . '" "' . $hashPath . '" 2>&1';
            $matchResult = trim(shell_exec($command));

            // Bersihkan file jepretan SEMENTARA saat login saja. 
            // (Foto master saat pendaftaran aman di public/faces)
            Storage::delete($path);
            if (file_exists($hashPath)) @unlink($hashPath);

            if ($matchResult === "100") {
                return response()->json(['success' => true, 'message' => 'Wajah Cocok!', 'user' => $user], 200);
            } elseif ($matchResult === "0") {
                return response()->json(['success' => false, 'message' => 'Wajah Tidak Dikenali.'], 401);
            } elseif ($matchResult === "TIDAK_ADA_WAJAH") {
                return response()->json(['success' => false, 'message' => 'Wajah tidak terdeteksi kamera. Coba di tempat yang lebih terang.'], 400);
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