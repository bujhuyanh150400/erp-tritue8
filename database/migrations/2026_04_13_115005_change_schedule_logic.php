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
        Schema::create('schedule_instance_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_instance_id')->constrained('schedule_instances')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('fee_amount', 10, 0)->nullable();

            $table->timestamps();

            // Chống duplicate: 1 học sinh chỉ có 1 record trong 1 buổi học
            $table->unique(['schedule_instance_id', 'student_id']);
        });

        Schema::table('schedule_instances', function (Blueprint $table) {
            // Cho phép class_id nhận giá trị NULL
            $table->foreignId('class_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_instance_participants');
        Schema::table('schedule_instances', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable(false)->change();
        });
    }
};
