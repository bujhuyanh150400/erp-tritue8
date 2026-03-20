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
        Schema::table('teachers', function (Blueprint $table) {
            // thêm column với default để tránh lỗi NOT NULL
            $table->string('bank_bin', 20)->default('')->after('address');
            // drop column cũ
            $table->dropColumn('bank_name');
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->string('bank_bin', 20)->default('')->after('role_type');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('bank_bin');

            // add lại bank_name (nên giống schema cũ)
            $table->string('bank_name')->after('address');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('bank_bin');
        });
    }
};
