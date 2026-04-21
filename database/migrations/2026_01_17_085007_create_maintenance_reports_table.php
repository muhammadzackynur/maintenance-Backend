<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Mengecek apakah tabel sudah ada. Jika belum, baru dibuat.
        // Jika sudah ada, Laravel akan mengabaikan blok ini (Data Anda aman!)
        if (!Schema::hasTable('maintenance_reports')) {
            Schema::create('maintenance_reports', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');
                $table->string('area')->nullable();
                $table->string('district')->nullable();
                $table->string('witel')->nullable();
                $table->string('sto')->nullable();
                $table->string('mitra_pelaksana')->nullable();
                $table->string('kategori_kegiatan')->nullable();
                $table->longText('uraian_pekerjaan')->nullable();
                $table->string('teknisi')->nullable();
                $table->string('status')->default('Pending');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_reports');
    }
};