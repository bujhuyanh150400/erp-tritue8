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
        Schema::create('staff_salary_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff');
            $table->string('month', 7);
            $table->decimal('base_salary', 10, 0);
            $table->decimal('bonus', 10, 0)->default(0);
            $table->decimal('penalty', 10, 0)->default(0);
            $table->decimal('advance_amount', 10, 0)->default(0);
            $table->decimal('total_amount', 10, 0);
            $table->decimal('paid_amount', 10, 0)->default(0);
            $table->unsignedTinyInteger('status');
            $table->boolean('is_locked')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_salary_invoices');
    }
};
