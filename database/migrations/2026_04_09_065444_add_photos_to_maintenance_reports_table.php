<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            // Mengecek apakah kolom foto_before belum ada, jika belum baru ditambah
            if (!Schema::hasColumn('maintenance_reports', 'foto_before')) {
                $table->string('foto_before')->nullable();
            }
            
            // Mengecek apakah kolom foto_progress belum ada
            if (!Schema::hasColumn('maintenance_reports', 'foto_progress')) {
                $table->string('foto_progress')->nullable();
            }
            
            // Mengecek apakah kolom foto_after belum ada
            if (!Schema::hasColumn('maintenance_reports', 'foto_after')) {
                $table->string('foto_after')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropColumn(['foto_before', 'foto_progress', 'foto_after']);
        });
    }
};