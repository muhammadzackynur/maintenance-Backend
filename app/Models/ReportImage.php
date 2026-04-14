<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportImage extends Model
{
    use HasFactory;

    // TAMBAHKAN BARIS INI UNTUK MEMBERI IZIN MASS ASSIGNMENT
    protected $fillable = [
        'maintenance_report_id',
        'image_path',
        'type'
    ];

    // Opsional: Relasi balik ke tabel maintenance_reports
    public function report()
    {
        return $this->belongsTo(MaintenanceReport::class, 'maintenance_report_id');
    }
}