<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceReport extends Model
{
    protected $fillable = [
        'user_id', 'lokasi_pekerjaan', 'latitude', 'longitude', 
        'jenis_maintenance', 'time_plan', 'deskripsi_masalah', 
        'teknisi', 'foto_before', 'foto_progress', 'foto_after'
    ];
}