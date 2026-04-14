<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use App\Models\ReportImage; 
use Illuminate\Support\Facades\Storage;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            // 1. Validasi data teks (Wajib) dan array foto (Opsional)
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
                
                // TAMBAHAN: Izinkan latitude dan longitude masuk
                'latitude' => 'nullable|string', 
                'longitude' => 'nullable|string',

                // Validasi agar bisa menerima banyak file (array)
                'foto_before.*' => 'nullable|image|max:5120',   
                'foto_progress.*' => 'nullable|image|max:5120',
                'foto_after.*' => 'nullable|image|max:5120',
            ]);

            // 2. Simpan data teks utama ke tabel maintenance_reports
            $report = MaintenanceReport::create($request->except(['foto_before', 'foto_progress', 'foto_after']));

            // 3. Proses looping untuk menyimpan banyak foto ke tabel relasi (report_images)
            $categories = ['foto_before', 'foto_progress', 'foto_after'];

            foreach ($categories as $category) {
                if ($request->hasFile($category)) {
                    foreach ($request->file($category) as $file) {
                        // Simpan file fisik
                        $path = $file->store('reports', 'public');

                        // Simpan path ke tabel terpisah agar bisa lebih dari 10 gambar
                        ReportImage::create([
                            'maintenance_report_id' => $report->id,
                            'image_path' => $path,
                            'type' => str_replace('foto_', '', $category) // hasil: before, progress, atau after
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Laporan dan semua foto berhasil dikirim!',
                'data' => $report->load('images') // Load gambar agar langsung muncul hasilnya
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        // Gunakan with('images') agar daftar foto muncul di Dashboard Admin/Teknisi
        $reports = MaintenanceReport::with('images')->orderBy('id', 'desc')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $reports
        ], 200);
    }

    public function updateData(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $dataToUpdate = $request->except(['_method', 'evidence_material', 'evidence_ukur', 'evidence_pendukung']);

        // Handle update file evidences (ZIP/RAR)
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

        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $categories = ['foto_before', 'foto_progress', 'foto_after'];
        $uploadedCount = 0;

        foreach ($categories as $category) {
            if ($request->hasFile($category)) {
                foreach ($request->file($category) as $file) {
                    $path = $file->store('reports', 'public');
                    ReportImage::create([
                        'maintenance_report_id' => $report->id,
                        'image_path' => $path,
                        // Menghapus kata 'foto_' untuk mendapatkan tipe aslinya
                        'type' => str_replace('foto_', '', $category) 
                    ]);
                    $uploadedCount++;
                }
            }
        }

        if ($uploadedCount == 0) {
            return response()->json(['success' => false, 'message' => 'Tidak ada foto yang dikirim'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "$uploadedCount foto baru berhasil ditambahkan!",
            'data' => $report->load('images')
        ], 200);
    }
}