<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use App\Models\ReportImage; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http; // Digunakan untuk memanggil API OneSignal
use Illuminate\Support\Facades\Log;  // Digunakan untuk mencatat error jika notifikasi gagal

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

            // 4. KIRIM NOTIFIKASI KE TIM ADMINISTRASI VIA ONESIGNAL
            $this->sendNotification($request->teknisi, $request->sto);

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
     * Fungsi Helper untuk mengirim notifikasi OneSignal
     */
    private function sendNotification($teknisi, $sto)
    {
        $appId = "c5e1b4de-5fdf-406e-ab45-7bb5b47ac450";
        $restApiKey = "os_v2_app_yxq3jxs735ag5k2fpo23i6wekcrw4wlm7qwusbncojyaqj5bxcaa42ookwm7ycnjwakivvtecnpjyofpakeu7nkxdcb6oy4uhuced5y";

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
                'headings' => ['en' => 'Laporan Maintenance Baru!'],
                'contents' => ['en' => "Teknisi $teknisi baru saja mengirim laporan pemeliharaan di STO $sto."],
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
}