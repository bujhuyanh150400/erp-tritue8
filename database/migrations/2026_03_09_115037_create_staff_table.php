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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('full_name', 255);
            $table->string('phone', 20);
            $table->unsignedTinyInteger('role_type');
            $table->string('bank_name', 100);
            $table->string('bank_account_number', 30);
            $table->string('bank_account_holder', 100);
            $table->unsignedTinyInteger('status');
            $table->date('joined_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
