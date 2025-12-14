<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin@system.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'permissions' => [],
        ]);

        // Seed initial data
        $this->call([
            InitialDataSeeder::class,
        ]);
    }
}
