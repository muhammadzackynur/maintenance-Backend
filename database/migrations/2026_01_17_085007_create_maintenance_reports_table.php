<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('maintenance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Tetap string (Tidak dienkripsi)
            
            // --- KOLOM DI BAWAH INI UBAH JADI LONGTEXT KARENA DIENKRIPSI ---
            $table->longText('area');
            $table->longText('district');
            $table->longText('witel');
            $table->longText('sto');
            $table->longText('mitra_pelaksana');
            $table->longText('kategori_kegiatan');
            $table->longText('uraian_pekerjaan');
            $table->longText('teknisi');
            
            // Kolom lokasi juga dienkripsi
            $table->longText('latitude')->nullable();
            $table->longText('longitude')->nullable();
            $table->longText('lokasi_pekerjaan')->nullable();
            // -------------------------------------------------------------

            $table->string('status')->default('PENDING'); // Tetap string

            // Bukti Evidence (Tetap string karena isinya path gambar .jpg/.png)
            $table->string('evidence_material')->nullable();
            $table->string('evidence_ukur')->nullable();
            $table->string('evidence_pendukung')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('maintenance_reports');
    }
};