<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // MUST run first - users table has FK to country_configs
        $this->call(CountryConfigSeeder::class);

        // Seed default categories
        $this->call(CategorySeeder::class);

        // Note: No test user created - users should register through the app
    }
}
