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
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_instance_id')->constrained('schedule_instances');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->date('session_date');
            $table->text('lesson_content')->nullable();
            $table->text('homework')->nullable();
            $table->text('next_session_note')->nullable();
            $table->text('general_note')->nullable();
            $table->unsignedTinyInteger('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['class_id', 'session_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
