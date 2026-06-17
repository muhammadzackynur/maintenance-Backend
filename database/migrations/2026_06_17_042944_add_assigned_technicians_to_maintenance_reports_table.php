<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            // Menyimpan multiple ID teknisi dalam bentuk array JSON
            $table->json('assigned_technicians')->nullable()->after('teknisi');
        });
    }

    public function down()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            $table->dropColumn('assigned_technicians');
        });
    }
};