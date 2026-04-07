<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tuition_invoices', 'export_count')) {
            Schema::table('tuition_invoices', function (Blueprint $table) {
                $table->unsignedInteger('export_count')->default(0)->after('note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tuition_invoices', 'export_count')) {
            Schema::table('tuition_invoices', function (Blueprint $table) {
                $table->dropColumn('export_count');
            });
        }
    }
};
