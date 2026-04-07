<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique(); 
            $table->string('name');
            $table->enum('role', ['Tim Lapangan', 'Tim Administrasi']);
            $table->longText('biometric_hash')->nullable(); // Kolom Sidik Jari Aplikasi
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};