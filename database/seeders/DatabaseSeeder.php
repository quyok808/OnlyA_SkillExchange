<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\SkillsTableSeeder;
use Database\Seeders\UserWithSkillsSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            SkillsTableSeeder::class,
            UserWithSkillsSeeder::class,
        ]);
    }
}
