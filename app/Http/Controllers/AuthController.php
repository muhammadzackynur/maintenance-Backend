<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller {
    
    public function login(Request $request) {
        $userIdInput = trim($request->user_id);
        $roleInput = strtolower(trim($request->role));

        // 1. Mapping input Flutter ke ENUM Database
        // Kata "admin" atau "administrasi" akan dianggap sebagai 'Tim Administrasi'
        $roleDb = '';
        if (strpos($roleInput, 'admin') !== false || strpos($roleInput, 'administrasi') !== false) {
            $roleDb = 'Tim Administrasi'; 
        } else {
            $roleDb = 'Tim Lapangan'; 
        }

        // 2. Cari User berdasarkan ID saja terlebih dahulu
        $user = User::where('user_id', $userIdInput)->first();

        // Jika ID tidak ditemukan sama sekali di tabel users
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Gagal: ID '$userIdInput' tidak terdaftar di database!"
            ], 401);
        }

        // 3. Jika ID ada, cek apakah Role-nya sesuai dengan jalur login yang dipilih
        if ($user->role !== $roleDb) {
            return response()->json([
                'success' => false,
                'message' => "Gagal: ID ini terdaftar sebagai '{$user->role}', bukan '$roleDb'."
            ], 401);
        }

        // 4. Jika ID dan Role cocok, Login Sukses
        return response()->json([
            'success' => true,
            'message' => 'Login Berhasil',
            'user' => $user
        ], 200);
    }

    public function update(Request $request, $id) {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $user->name = $request->name;
        $user->save();
        return response()->json(['success' => true, 'message' => 'Updated', 'user' => $user], 200);
    }

    // --- FUNGSI UNTUK MENDAFTARKAN PENGGUNA BARU ---
    public function register(Request $request) {
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|string|max:50|unique:users', // Pastikan ID tidak boleh sama
            'role' => 'required|in:Tim Lapangan,Tim Administrasi',
        ]);

        // Simpan ke database
        $user = User::create([
            'name' => $request->name,
            'user_id' => $request->user_id,
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil didaftarkan!',
            'user' => $user
        ], 201);
    }
}