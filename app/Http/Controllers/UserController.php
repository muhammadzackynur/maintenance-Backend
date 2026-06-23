<?php

namespace App\Http\Controllers;

use App\Models\User; 
use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Mengambil semua data pengguna untuk fitur Jadwal / Tim Lapangan
     */
    public function index()
    {
        $users = User::orderBy('user_id', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    /**
     * Mengambil data pencapaian (achievements) khusus Tim Lapangan
     */
    public function getAchievements($userId)
    {
        // 1. Kontributor Aktif: Total semua laporan yang disubmit pengguna ini
        $totalSubmitted = MaintenanceReport::where('user_id', $userId)->count();

        // 2. Bintang Lapangan: Total laporan dengan status CLOSE
        $totalClosed = MaintenanceReport::where('user_id', $userId)
                                        ->where('status', 'CLOSE')
                                        ->count();

        // 3. Pekerja Tanpa Cacat: Hitung streak beruntun status CLOSE dari laporan terbaru
        $reports = MaintenanceReport::where('user_id', $userId)
                                    ->orderBy('created_at', 'desc')
                                    ->get();
        
        $currentStreak = 0;
        foreach ($reports as $report) {
            if ($report->status === 'CLOSE') {
                $currentStreak++;
            } elseif ($report->status === 'OPEN') {
                continue; 
            } else {
                break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_submitted' => $totalSubmitted,
                'total_closed' => $totalClosed,
                'current_streak' => $currentStreak
            ]
        ], 200);
    }

    /**
     * Memperbarui Foto Profil Pengguna
     * Jika $id tidak dikirim, maka akan otomatis memperbarui user yang sedang login
     */
    public function updatePhoto(Request $request, $id = null)
    {
        // Validasi file agar hanya menerima gambar
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $targetId = $id ?? Auth::id();
        $user = User::find($targetId);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        if ($request->hasFile('photo')) {
            // Hapus foto lama dari storage jika ada (mencegah storage penuh)
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            // Simpan foto baru ke folder 'profile_photos' di disk public
            $path = $request->file('photo')->store('profile_photos', 'public');
            
            $user->photo = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diperbarui',
                // Menggunakan asset untuk generate URL lengkap
                'photo_url' => asset('storage/' . $path)
            ], 200);
        }

        return response()->json(['success' => false, 'message' => 'File foto tidak terunggah'], 400);
    }

    /**
     * Menghapus Pengguna Secara Permanen
     */
    public function destroy(Request $request, $id)
    {
        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        // Proteksi: Admin tidak dapat menghapus akunnya sendiri
        if (Auth::id() == $id) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.'
            ], 403);
        }

        // Bersihkan file foto profil di storage
        if ($targetUser->photo && Storage::disk('public')->exists($targetUser->photo)) {
            Storage::disk('public')->delete($targetUser->photo);
        }

        // Hapus data terkait (Maintenance Report)
        $userIdentifier = $targetUser->user_id ?? $targetUser->id;
        MaintenanceReport::where('user_id', $userIdentifier)->delete();

        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus permanen.'
        ], 200);
    }
}