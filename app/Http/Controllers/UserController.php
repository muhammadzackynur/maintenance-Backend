<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User; 
use App\Models\MaintenanceReport;

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
        // Mengambil data diurutkan dari yang paling baru
        $reports = MaintenanceReport::where('user_id', $userId)
                                    ->orderBy('created_at', 'desc')
                                    ->get();
        
        $currentStreak = 0;
        foreach ($reports as $report) {
            if ($report->status === 'CLOSE') {
                $currentStreak++;
            } elseif ($report->status === 'OPEN') {
                // Jika masih OPEN, kita abaikan (tidak memutus streak karena belum selesai)
                continue; 
            } else {
                // Jika statusnya DITOLAK / REVISI / selain OPEN dan CLOSE, streak putus
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
     */
    public function updatePhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        if ($request->hasFile('photo')) {
            // Hapus foto lama dari storage jika ada
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $path = $request->file('photo')->store('profile_photos', 'public');
            $user->photo = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diperbarui',
                'photo_url' => asset('storage/' . $path)
            ], 200);
        }

        return response()->json(['success' => false, 'message' => 'File foto tidak unggah'], 400);
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

        // Bersihkan file foto profil di storage
        if ($targetUser->photo && Storage::disk('public')->exists($targetUser->photo)) {
            Storage::disk('public')->delete($targetUser->photo);
        }

        // Hapus semua laporan milik user tersebut agar tidak menjadi orphan data
        MaintenanceReport::where('user_id', $targetUser->user_id)->delete();

        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus permanen.'
        ], 200);
    }
}