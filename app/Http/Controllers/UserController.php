<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Mengimpor model User

class UserController extends Controller
{
    /**
     * Mengambil semua data pengguna untuk fitur Jadwal / Tim Lapangan
     */
    public function index()
    {
        // Mengambil semua user dari database, diurutkan berdasarkan user_id (abjad)
        $users = User::orderBy('user_id', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }
}