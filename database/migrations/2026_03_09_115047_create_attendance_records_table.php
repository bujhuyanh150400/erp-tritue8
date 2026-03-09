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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions');
            $table->foreignId('student_id')->constrained('students');
            $table->unsignedTinyInteger('status');
            $table->time('check_in_time')->nullable();
            $table->boolean('is_fee_counted')->default(false);
            $table->text('teacher_comment')->nullable();
            $table->text('private_note')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
