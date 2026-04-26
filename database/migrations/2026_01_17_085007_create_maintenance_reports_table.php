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
            $table->string('user_id');
            $table->string('area');
            $table->string('district');
            $table->string('witel');
            $table->string('sto');
            $table->string('mitra_pelaksana');
            $table->string('kategori_kegiatan');
            $table->text('uraian_pekerjaan');
            $table->string('teknisi');
            
            // Kolom lokasi
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->text('lokasi_pekerjaan')->nullable();

            $table->string('status')->default('PENDING');

            // Bukti Evidence
            $table->string('evidence_material')->nullable();
            $table->string('evidence_ukur')->nullable();
            $table->string('evidence_pendukung')->nullable();
            
            $table->timestamps();
        });
    } // <-- Cukup satu kurung kurawal tutup di sini

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