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
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('student_id')->constrained('students');
            $table->decimal('fee_per_session', 10, 0)->nullable();
            $table->date('fee_effective_from')->nullable();
            $table->date('fee_effective_to')->nullable();
            $table->timestamp('enrolled_at');
            $table->date('left_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['class_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};
