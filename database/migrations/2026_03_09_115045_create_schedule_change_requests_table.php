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
        Schema::create('schedule_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_instance_id')->constrained('schedule_instances');
            $table->foreignId('requested_by')->constrained('teachers');
            $table->date('proposed_date');
            $table->time('proposed_start_time');
            $table->time('proposed_end_time');
            $table->foreignId('proposed_room_id')->nullable()->constrained('rooms');
            $table->foreignId('proposed_teacher_id')->nullable()->constrained('teachers');
            $table->text('reason');
            $table->unsignedTinyInteger('status')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_change_requests');
    }
};
