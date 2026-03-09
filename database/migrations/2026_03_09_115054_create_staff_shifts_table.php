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
        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->date('shift_date');
            $table->dateTime('check_in_time');
            $table->dateTime('check_out_time');
            $table->decimal('total_hours', 4, 2);
            $table->decimal('hourly_rate_snapshot', 10, 0);
            $table->decimal('total_salary', 10, 0);
            $table->unsignedTinyInteger('status');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'shift_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_shifts');
    }
};
