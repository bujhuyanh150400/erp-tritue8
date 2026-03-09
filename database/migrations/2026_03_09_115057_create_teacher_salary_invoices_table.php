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
        Schema::create('teacher_salary_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('class_id')->constrained('classes');
            $table->string('month', 7);
            $table->integer('total_sessions');
            $table->decimal('bonus', 10, 0)->default(0);
            $table->decimal('penalty', 10, 0)->default(0);
            $table->decimal('total_amount', 10, 0);
            $table->decimal('paid_amount', 10, 0)->default(0);
            $table->unsignedTinyInteger('status');
            $table->boolean('is_locked')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'class_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_salary_invoices');
    }
};
