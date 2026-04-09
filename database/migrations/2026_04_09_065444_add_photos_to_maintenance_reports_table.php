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
    Schema::table('maintenance_reports', function (Blueprint $table) {
        $table->string('foto_before')->nullable();
        $table->string('foto_progress')->nullable();
        $table->string('foto_after')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('maintenance_reports', function (Blueprint $table) {
            //
        });
    }
};
