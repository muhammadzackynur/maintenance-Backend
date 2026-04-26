<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use App\Models\ReportImage; 
use App\Models\AppNotification; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            // 1. Validasi data teks dan foto
            $validated = $request->validate([
                'user_id' => 'required',
                'area' => 'required|string',
                'district' => 'required|string',
                'witel' => 'required|string',
                'sto' => 'required|string',
                'mitra_pelaksana' => 'required|string',
                'kategori_kegiatan' => 'required|string',
                'uraian_pekerjaan' => 'required|string',
                'teknisi' => 'required|string',
                
                // Koordinat Lokasi
                'latitude' => 'nullable|string', 
                'longitude' => 'nullable|string',
                'lokasi_pekerjaan' => 'nullable|string',

                // Validasi Foto
                'foto_before' => 'required|array|min:1', 
                'foto_before.*' => 'image|max:5120',   
                
                'foto_progress.*' => 'nullable|image|max:5120',
                'foto_after.*' => 'nullable|image|max:5120',
            ], [
                'foto_before.required' => 'Bukti foto "Before" wajib diunggah!',
                'foto_before.min' => 'Minimal harus mengunggah 1 foto "Before".',
            ]);

            // 2. Simpan data teks utama ke tabel maintenance_reports
            $report = MaintenanceReport::create($request->except(['foto_before', 'foto_progress', 'foto_after']));

            // 3. Proses looping untuk menyimpan banyak foto ke tabel relasi
            $categories = ['foto_before', 'foto_progress', 'foto_after'];

            foreach ($categories as $category) {
                if ($request->hasFile($category)) {
                    foreach ($request->file($category) as $file) {
                        $path = $file->store('reports', 'public');

                        ReportImage::create([
                            'maintenance_report_id' => $report->id,
                            'image_path' => $path,
                            'type' => str_replace('foto_', '', $category)
                        ]);
                    }
                }
            }

            // 4. KIRIM NOTIFIKASI PUSH VIA ONESIGNAL (Pop-up di layar untuk Admin)
            $this->sendNotification($request->uraian_pekerjaan, $request->sto);

            // 5. SIMPAN NOTIFIKASI KE DATABASE (Untuk list di ikon lonceng Admin)
            AppNotification::create([
                'user_id' => 'admin', // <-- Disimpan khusus untuk admin
                'title' => 'Laporan Maintenance Baru!',
                'message' => $request->uraian_pekerjaan . "\nSTO " . $request->sto,
                'is_read' => false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan dan bukti foto berhasil dikirim!',
                'data' => $report->load('images') 
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: Bukti foto belum lengkap.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fungsi Helper untuk mengirim notifikasi laporan baru ke Tim Administrasi
     */
    private function sendNotification($uraian_pekerjaan, $sto)
    {
        $appId = "012d1524-5b9a-4834-8dab-8741b8dbd0c1";
        $restApiKey = "os_v2_app_aewrkjc3tjedjdnlq5a3rw6qyh5e33rkj22eqrnme6icgea23fjvilujduc6ov6lf6srrpojxsygk3qzn5mmpedmais75lpxn5m2iii";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $restApiKey,
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $appId,
                'filters' => [
                    ['field' => 'tag', 'key' => 'role', 'relation' => '=', 'value' => 'tim_administrasi']
                ],
                'target_channel' => 'push', // <-- Menegaskan ini adalah push notification
                'headings' => ['en' => 'Laporan Maintenance Baru!'],
                'contents' => ['en' => $uraian_pekerjaan . "\nSTO " . $sto],

                // --- TAMBAHAN BARU: Memaksa Pop-up Prioritas Tinggi di Android ---
                'android_visibility' => 1,
                'priority' => 10,
                'android_channel_id' => 'channel_vip_super', // <-- NAMA CHANNEL BARU!
                // ------------------------------------------------------------------
            ]);

            if (!$response->successful()) {
                Log::error('OneSignal Notification Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('OneSignal Exception: ' . $e->getMessage());
        }
    }

    public function index()
    {
        $reports = MaintenanceReport::with('images')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $reports], 200);
    }

    public function updateData(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);

        $dataToUpdate = $request->except(['_method', 'evidence_material', 'evidence_ukur', 'evidence_pendukung']);
        $files = ['evidence_material', 'evidence_ukur', 'evidence_pendukung'];
        foreach ($files as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = 'MAINT-' . $id . '_' . ucfirst(str_replace('evidence_', '', $field)) . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('evidences', $filename, 'public');
                $dataToUpdate[$field] = $path;
            }
        }

        $report->update($dataToUpdate);
        return response()->json(['success' => true, 'data' => $report], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['message' => 'Data tidak ditemukan'], 404);

        $report->status = $request->status;
        $report->save();

        // Kirim notifikasi status HANYA ke pembuat laporan
        $this->sendStatusNotification($report->user_id, $report->id, $request->status);

        return response()->json(['message' => 'Status diperbarui', 'data' => $report], 200);
    }

    public function addPhotos(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);

        $categories = ['foto_before', 'foto_progress', 'foto_after'];
        $uploadedCount = 0;
        foreach ($categories as $category) {
            if ($request->hasFile($category)) {
                foreach ($request->file($category) as $file) {
                    $path = $file->store('reports', 'public');
                    ReportImage::create([
                        'maintenance_report_id' => $report->id,
                        'image_path' => $path,
                        'type' => str_replace('foto_', '', $category) 
                    ]);
                    $uploadedCount++;
                }
            }
        }

        return $uploadedCount == 0 
            ? response()->json(['success' => false, 'message' => 'Tidak ada foto'], 400)
            : response()->json(['success' => true, 'message' => "$uploadedCount foto berhasil ditambahkan!", 'data' => $report->load('images')], 200);
    }

    // ====================================================================
    // FUNGSI UNTUK FITUR LONCENG NOTIFIKASI DI FRONTEND
    // ====================================================================
    public function getNotifications(Request $request)
    {
        $userId = $request->query('user_id', 'admin'); 

        $notifs = AppNotification::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
        $unreadCount = AppNotification::where('user_id', $userId)->where('is_read', false)->count();

        return response()->json([
            'success' => true, 
            'data' => $notifs, 
            'unread_count' => $unreadCount
        ], 200);
    }

    public function markAsRead($id)
    {
        $notif = AppNotification::find($id);
        if ($notif) {
            $notif->is_read = true;
            $notif->save();
        }
        return response()->json(['success' => true]);
    }

    // ====================================================================
    // FUNGSI HELPER NOTIFIKASI STATUS KE TIM LAPANGAN
    // ====================================================================
    /**
     * Fungsi Helper untuk mengirim notifikasi status ke Tim Lapangan spesifik
     */
    private function sendStatusNotification($targetUserId, $reportId, $status)
    {
        $appId = "012d1524-5b9a-4834-8dab-8741b8dbd0c1";
        $restApiKey = "os_v2_app_aewrkjc3tjedjdnlq5a3rw6qyh5e33rkj22eqrnme6icgea23fjvilujduc6ov6lf6srrpojxsygk3qzn5mmpedmais75lpxn5m2iii";

        // Sesuaikan bahasa status
        $statusText = strtolower($status) == 'verified' ? 'Diverifikasi' : 'Ditolak';
        
        // Format ID Laporan (Misal: MAINT-001)
        $formattedId = "MAINT-" . str_pad($reportId, 3, '0', STR_PAD_LEFT);

        // Simpan ke database khusus untuk user_id teknisi tersebut
        AppNotification::create([
            'user_id' => (string)$targetUserId,
            'title' => "Laporan Anda $statusText!",
            'message' => "Laporan ID $formattedId telah $statusText oleh Admin.",
            'is_read' => false
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $restApiKey,
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', [
                'app_id' => $appId,
                
                // MENGGUNAKAN METODE TARGETING TERBARU ONESIGNAL
                'include_aliases' => [
                    'external_id' => [(string)$targetUserId]
                ],
                'target_channel' => 'push',
                
                'headings' => ['en' => "Laporan Anda $statusText!"],
                'contents' => ['en' => "Laporan dengan ID $formattedId telah $statusText oleh Tim Administrasi."],

                // --- MEMASTIKAN POP-UP JUGA UNTUK TIM LAPANGAN ---
                'android_visibility' => 1,
                'priority' => 10,
                'android_channel_id' => 'channel_vip_super', // <-- NAMA CHANNEL BARU!
                // --------------------------------------------------
            ]);

            if (!$response->successful()) {
                Log::error('OneSignal Status Notification Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('OneSignal Status Exception: ' . $e->getMessage());
        }
    }
}