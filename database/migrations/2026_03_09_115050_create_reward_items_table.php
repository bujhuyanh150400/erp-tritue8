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
        Schema::create('reward_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('points_required');
            $table->unsignedTinyInteger('reward_type');
            $table->decimal('discount_amount', 10, 0)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('reward_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_items');
    }
};
