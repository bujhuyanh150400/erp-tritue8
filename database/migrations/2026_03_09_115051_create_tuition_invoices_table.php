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
        Schema::create('tuition_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 20);
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('class_id')->constrained('classes');
            $table->string('month', 7);
            $table->integer('total_sessions');
            $table->integer('attended_sessions');
            $table->decimal('total_study_fee', 10, 0);
            $table->decimal('discount_amount', 10, 0)->default(0);
            $table->decimal('previous_debt', 10, 0)->default(0);
            $table->decimal('total_amount', 10, 0);
            $table->decimal('paid_amount', 10, 0)->default(0);
            $table->unsignedTinyInteger('status');
            $table->boolean('is_locked')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'class_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_invoices');
    }
};
