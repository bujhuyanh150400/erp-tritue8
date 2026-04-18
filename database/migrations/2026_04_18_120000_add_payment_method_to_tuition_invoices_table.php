<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tuition_invoices', 'payment_method')) {
            Schema::table('tuition_invoices', function (Blueprint $table) {
                $table->unsignedTinyInteger('payment_method')->nullable()->after('paid_amount');
            });
        }

        DB::statement("
            UPDATE tuition_invoices
            SET payment_method = latest_log.payment_method
            FROM (
                SELECT DISTINCT ON (invoice_id)
                    invoice_id,
                    payment_method
                FROM tuition_invoice_logs
                WHERE is_cancelled = false
                ORDER BY invoice_id, paid_at DESC, id DESC
            ) as latest_log
            WHERE latest_log.invoice_id = tuition_invoices.id
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('tuition_invoices', 'payment_method')) {
            Schema::table('tuition_invoices', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
