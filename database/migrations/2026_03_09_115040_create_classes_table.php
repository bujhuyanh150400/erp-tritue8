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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 50);
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->unsignedTinyInteger('grade_level');
            $table->decimal('base_fee_per_session', 10, 0);
            $table->decimal('teacher_salary_per_session', 10, 0);
            $table->unsignedTinyInteger('max_students')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->date('start_at');
            $table->date('end_at')->nullable();
            $table->timestamps();
            $table->index('grade_level');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
