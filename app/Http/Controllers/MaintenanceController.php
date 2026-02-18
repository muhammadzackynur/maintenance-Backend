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
}