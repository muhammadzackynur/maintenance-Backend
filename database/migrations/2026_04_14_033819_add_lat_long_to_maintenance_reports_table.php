<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            // Cek apakah kolom latitude belum ada, jika belum baru ditambah
            if (!Schema::hasColumn('maintenance_reports', 'latitude')) {
                $table->string('latitude')->nullable();
            }
            
            // Cek apakah kolom longitude belum ada, jika belum baru ditambah
            if (!Schema::hasColumn('maintenance_reports', 'longitude')) {
                $table->string('longitude')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};