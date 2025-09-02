<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'email' => 'admin@menadevs.com',
            'password' => Hash::make('password'),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role' => 'system_admin',
            'is_active' => true,
        ]);

        // Create project owner user
        User::create([
            'email' => 'project@menadevs.com',
            'password' => Hash::make('password'),
            'first_name' => 'Project',
            'last_name' => 'Owner',
            'role' => 'project_owner',
            'is_active' => true,
        ]);

        // Create 10 regular users
        for ($i = 1; $i <= 10; $i++) {
            User::create([
                'email' => "user{$i}@menadevs.com",
                'password' => Hash::make('password'),
                'first_name' => "Regular{$i}",
                'last_name' => 'User',
                'role' => 'user',
                'is_active' => true,
            ]);
        }
    }
}
