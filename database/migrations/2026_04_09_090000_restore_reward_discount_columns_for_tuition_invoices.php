<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reward_items', 'discount_amount')) {
            Schema::table('reward_items', function (Blueprint $table) {
                $table->decimal('discount_amount', 10, 0)->nullable()->after('note');
            });
        }

        if (! Schema::hasColumn('reward_redemptions', 'invoice_id')) {
            Schema::table('reward_redemptions', function (Blueprint $table) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('processed_by')
                    ->constrained('tuition_invoices');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reward_redemptions', 'invoice_id')) {
            Schema::table('reward_redemptions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }

        if (Schema::hasColumn('reward_items', 'discount_amount')) {
            Schema::table('reward_items', function (Blueprint $table) {
                $table->dropColumn('discount_amount');
            });
        }
    }
};
