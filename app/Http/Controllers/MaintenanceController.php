<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        try {
            // 1. Validasi data teks dan file foto yang masuk dari Flutter
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
                // Validasi foto (opsional/nullable, harus berupa gambar, max 5MB)
                'foto_before' => 'nullable|image|max:5120',   
                'foto_progress' => 'nullable|image|max:5120',
                'foto_after' => 'nullable|image|max:5120',
            ]);

            // 2. Proses menyimpan file foto ke folder storage/app/public/reports (jika ada fotonya)
            if ($request->hasFile('foto_before')) {
                $validated['foto_before'] = $request->file('foto_before')->store('reports', 'public');
            }
            if ($request->hasFile('foto_progress')) {
                $validated['foto_progress'] = $request->file('foto_progress')->store('reports', 'public');
            }
            if ($request->hasFile('foto_after')) {
                $validated['foto_after'] = $request->file('foto_after')->store('reports', 'public');
            }

            // 3. Proses menyimpan semua data teks dan path foto ke tabel maintenance_reports
            $report = MaintenanceReport::create($validated);

            // 4. Memberikan respon sukses ke Flutter
            return response()->json([
                'success' => true,
                'message' => 'Laporan dan Foto berhasil dikirim!',
                'data' => $report
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Menangkap error jika ada kolom wajib yang kosong atau foto terlalu besar
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal, periksa kelengkapan data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Menangkap error server (misal database mati atau folder belum di-link)
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        // Pastikan pakai orderBy agar data terbaru ada di paling atas
        $reports = MaintenanceReport::orderBy('id', 'desc')->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $reports
        ], 200);
    }

    public function updateData(Request $request, $id)
    {
        $report = MaintenanceReport::find($id);

        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Data laporan tidak ditemukan'], 404);
        }

        // Ambil semua data teks yang dikirim
        $dataToUpdate = $request->except(['_method', 'evidence_material', 'evidence_ukur', 'evidence_pendukung']);

        // --- PROSES UPLOAD FILE DENGAN MEMPERTAHANKAN NAMA ASLI ---
        
        if ($request->hasFile('evidence_material')) {
            $file = $request->file('evidence_material');
            // Format: MAINT-{ID}_Material_NamaAsli.zip
            $filename = 'MAINT-' . $id . '_Material_' . $file->getClientOriginalName();
            $path = $file->storeAs('evidences', $filename, 'public');
            $dataToUpdate['evidence_material'] = $path;
        }
        
        if ($request->hasFile('evidence_ukur')) {
            $file = $request->file('evidence_ukur');
            // Format: MAINT-{ID}_Ukur_NamaAsli.zip
            $filename = 'MAINT-' . $id . '_Ukur_' . $file->getClientOriginalName();
            $path = $file->storeAs('evidences', $filename, 'public');
            $dataToUpdate['evidence_ukur'] = $path;
        }

        if ($request->hasFile('evidence_pendukung')) {
            $file = $request->file('evidence_pendukung');
            // Format: MAINT-{ID}_Pendukung_NamaAsli.zip
            $filename = 'MAINT-' . $id . '_Pendukung_' . $file->getClientOriginalName();
            $path = $file->storeAs('evidences', $filename, 'public');
            $dataToUpdate['evidence_pendukung'] = $path;
        }

        // Simpan ke database
        $report->update($dataToUpdate);

        return response()->json([
            'success' => true,
            'message' => 'Data dan Evidence berhasil diperbarui!',
            'data' => $report
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        // 1. Cari data berdasarkan ID
        $report = MaintenanceReport::find($id);

        if (!$report) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        // 2. Update kolom status
        $report->status = $request->status;
        $report->save();

        // 3. Kembalikan respon sukses
        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'data' => $report
        ], 200);
    }
}