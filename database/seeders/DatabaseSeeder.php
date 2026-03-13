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
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')],
        );

        $this->call(LibrarySkillSeeder::class);
        $this->call(ComplianceSkillSeeder::class);
        $this->call(AgentSeeder::class);
        $this->call(PlanSeeder::class);
    }
}
