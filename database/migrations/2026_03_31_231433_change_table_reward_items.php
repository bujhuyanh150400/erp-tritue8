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
        Schema::table('reward_items', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
            $table->text('note')->nullable()->after('is_active');
        });
        Schema::table('reward_redemptions', function (Blueprint $table) {
            if (Schema::hasColumn('reward_redemptions', 'invoice_id')) {
                $table->dropColumn('invoice_id');
            }
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 0)->nullable()->after('is_active');
            $table->dropColumn('note');
        });
    }
};
