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
        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'next_session_note')){
                $table->dropColumn('next_session_note');
            }
            $table->jsonb('lesson_content_files')->nullable()->after('lesson_content');
            $table->jsonb('homework_files')->nullable()->after('homework');
            $table->jsonb('general_note_files')->nullable()->after('general_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['lesson_content_files', 'homework_files', 'general_note_files']);
        });
    }
};
