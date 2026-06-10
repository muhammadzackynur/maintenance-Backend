<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\MaintenanceReport; // Tambahkan import model laporan

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
}