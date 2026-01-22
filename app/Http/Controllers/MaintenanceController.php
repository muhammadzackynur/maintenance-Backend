<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceReport;
use Illuminate\Support\Facades\Storage;

class MaintenanceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'lokasi_pekerjaan' => 'required',
            'jenis_maintenance' => 'required',
            'time_plan' => 'required|date',
            'deskripsi_masalah' => 'nullable',
            'teknisi' => 'required',
            'foto_before' => 'required|image|mimes:jpeg,png,jpg',
            'foto_progress' => 'required|image|mimes:jpeg,png,jpg',
            'foto_after' => 'required|image|mimes:jpeg,png,jpg',
        ]);

        // Proses Upload Foto
        $pathBefore = $request->file('foto_before')->store('reports', 'public');
        $pathProgress = $request->file('foto_progress')->store('reports', 'public');
        $pathAfter = $request->file('foto_after')->store('reports', 'public');

        $report = MaintenanceReport::create(array_merge($validated, [
            'foto_before' => $pathBefore,
            'foto_progress' => $pathProgress,
            'foto_after' => $pathAfter,
        ]));

        return response()->json(['message' => 'Laporan berhasil disimpan', 'data' => $report], 201);
    }
}