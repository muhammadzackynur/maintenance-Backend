<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller {
    public function login(Request $request) {
        $user = User::where('user_id', $request->user_id)
                    ->where('role', $request->role)
                    ->first();

        if ($user) {
            return response()->json([
                'success' => true,
                'message' => 'Login Berhasil',
                'user' => $user
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'ID atau Role tidak cocok'
        ], 401);
    }
}