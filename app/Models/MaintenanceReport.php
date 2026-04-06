<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceReport extends Model
{
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
        'status',
        'evidence_material',
        'evidence_ukur',
        'evidence_pendukung'
    ];
}