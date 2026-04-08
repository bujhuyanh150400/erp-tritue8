<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
 // Add unique constraint to linked_makeup_for column
        Schema::table('schedule_instances', function (Blueprint $table) {
            $table->unique('linked_makeup_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique constraint from linked_makeup_for column
        Schema::table('schedule_instances', function (Blueprint $table) {
            $table->dropUnique('linked_makeup_for');
        });
    }
};
