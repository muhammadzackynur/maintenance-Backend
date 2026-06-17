<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceReport extends Model
{
    use HasFactory;

    // Pastikan semua nama kolom yang ada di validasi terdaftar di sini
    protected $fillable = [
        'user_id', 
        'area', 
        'district', 
        'witel', 
        'sto', 
        'mitra_pelaksana', 
        'kategori_kegiatan', 
        'uraian_pekerjaan', 
        'teknisi',
        'assigned_technicians', // <-- Tambahan kolom baru untuk assign teknisi
        'foto_before', 'foto_progress', 'foto_after',
        'latitude', 'longitude',
        'status',
        'lokasi_pekerjaan',
        'evidence_material', 'evidence_ukur', 'evidence_pendukung'
    ];

    // Ubah format JSON ke Array secara otomatis
    protected $casts = [
        'assigned_technicians' => 'array',
    ];

    public function images() 
    {
        return $this->hasMany(ReportImage::class);
    }
}