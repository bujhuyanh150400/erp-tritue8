<?php

namespace Database\Seeders;

use App\Constants\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'username' => 'admin@admin.com',
            'password' => Hash::make('Test123456789@'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }
}
