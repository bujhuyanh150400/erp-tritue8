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
        Schema::create('schedule_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('template_id')->nullable()->constrained('class_schedule_templates');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('original_teacher_id')->constrained('teachers');
            $table->decimal('teacher_salary_snapshot', 10, 0);
            $table->decimal('custom_salary', 10, 0)->nullable();
            $table->unsignedTinyInteger('schedule_type');
            $table->unsignedTinyInteger('status');
            $table->foreignId('linked_makeup_for')->nullable()->constrained('schedule_instances');
            $table->unsignedTinyInteger('fee_type');
            $table->decimal('custom_fee_per_session', 10, 0)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->index(['class_id', 'date']);
            $table->index(['room_id', 'date', 'start_time', 'end_time'], 'schedule_room_time_idx');
            $table->index(['teacher_id', 'date', 'start_time', 'end_time'], 'schedule_teacher_time_idx');
            $table->index(['schedule_type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_instances');
    }
};
