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
        Schema::table('teacher_salary_configs', function (Blueprint $table) {
            // 1. Xóa các khóa ngoại và cột cũ
            // Lưu ý: Tên index mặc định của Laravel là table_column_foreign
            $table->dropForeign(['class_id']);
            $table->dropColumn(['class_id', 'effective_from', 'effective_to']);

            // 2. Thêm cột mới
            // salary_type thường dùng tinyInteger cho các hằng số Enum
            $table->unsignedTinyInteger('salary_type')
                ->nullable()
                ->after('salary_per_session')
                ->comment('Loại lương: Theo buổi, theo tháng, v.v.');

        });

        Schema::table('classes', function (Blueprint $table) {
            if (Schema::hasColumn('classes', 'teacher_salary_per_session')) {
                $table->dropColumn('teacher_salary_per_session');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_salary_configs', function (Blueprint $table) {
            // Khôi phục lại các cột cũ nếu cần rollback
            $table->unsignedBigInteger('class_id')->nullable()->after('teacher_id');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->foreign('class_id')->references('id')->on('classes');
            $table->index(['teacher_id', 'class_id']);

            // Xóa cột mới
            $table->dropColumn('salary_type');
        });
    }
};
