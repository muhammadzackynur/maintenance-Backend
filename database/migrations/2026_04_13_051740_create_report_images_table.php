<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Pastikan nama tabelnya di sini adalah 'report_images'
        if (!Schema::hasTable('report_images')) {
            Schema::create('report_images', function (Blueprint $table) {
                $table->id();
                // Relasi ke tabel maintenance_reports
                $table->foreignId('maintenance_report_id')->constrained('maintenance_reports')->onDelete('cascade');
                $table->string('image_path');
                $table->string('type'); // Untuk menyimpan: before, progress, atau after
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('report_images');
    }
};