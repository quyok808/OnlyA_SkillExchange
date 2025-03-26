<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class SkillsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            'Coding',
            'Design',
            'Marketing',
            'Sales',
            'Project Management',
            'Data Analysis',
        ];

        foreach ($skills as $skillName) {
            Skill::create([
                'id' => Str::uuid(),
                'name' => $skillName,
            ]);
        }

        $this->command->info('Các kỹ năng đã được tạo thành công.');
    }
}
