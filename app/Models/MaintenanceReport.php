<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceReport extends Model
{
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
        'foto_before', 'foto_progress', 'foto_after',
        'latitude', 'longitude',
        'status',
        'lokasi_pekerjaan'

    ];

    public function images() {
    return $this->hasMany(ReportImage::class);
}
}
