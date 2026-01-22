<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // ID Pengirim
            $table->text('lokasi_pekerjaan');
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('jenis_maintenance');
            $table->date('time_plan');
            $table->text('deskripsi_masalah');
            $table->text('teknisi'); // Simpan sebagai JSON atau string dipisahkan koma
            $table->string('foto_before')->nullable();
            $table->string('foto_progress')->nullable();
            $table->string('foto_after')->nullable();
            $table->timestamps();

            // Relasi ke tabel users
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('maintenance_reports');
    }
};