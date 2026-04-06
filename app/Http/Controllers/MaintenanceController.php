<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        // Validasi input (Tanpa Foto)
        $validated = $request->validate([
            'user_id' => 'required',
            'area' => 'required',
            'district' => 'required',
            'witel' => 'required',
            'sto' => 'required',
            'mitra_pelaksana' => 'required',
            'kategori_kegiatan' => 'required',
            'uraian_pekerjaan' => 'required',
            'teknisi' => 'required',
        ]);

        // Simpan ke database tanpa proses upload gambar
        $report = MaintenanceReport::create($validated);

        return response()->json([
            'message' => 'Laporan berhasil disimpan',
            'data' => $report
        ], 201);
    }

    public function index()
    {
        $reports = MaintenanceReport::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'message' => 'List Laporan',
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
}  