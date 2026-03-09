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
        Schema::create('expense_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories');
            $table->string('title', 255);
            $table->unsignedTinyInteger('status');
            $table->string('month', 7);
            $table->decimal('amount', 10, 0);
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->unsignedTinyInteger('payment_method');
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_invoices');
    }
};
