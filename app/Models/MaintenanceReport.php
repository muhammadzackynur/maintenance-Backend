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
        'teknisi'
    ];
}