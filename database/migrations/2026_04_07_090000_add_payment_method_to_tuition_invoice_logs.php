<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tuition_invoice_logs', 'payment_method')) {
            Schema::table('tuition_invoice_logs', function (Blueprint $table) {
                $table->unsignedTinyInteger('payment_method')->default(0)->after('cancel_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tuition_invoice_logs', 'payment_method')) {
            Schema::table('tuition_invoice_logs', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};
