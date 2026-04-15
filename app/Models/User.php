<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'role',
        'biometric_hash', // Kolom wajib untuk menyimpan titik wajah
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
}