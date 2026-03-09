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
        Schema::create('teacher_salary_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('teacher_salary_invoices');
            $table->decimal('amount', 10, 0);
            $table->dateTime('paid_at');
            $table->text('note')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_salary_invoice_logs');
    }
};
