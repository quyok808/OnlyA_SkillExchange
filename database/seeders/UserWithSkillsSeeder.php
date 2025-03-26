<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Skill;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\SkillsTableSeeder;

class UserWithSkillsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tìm ba kỹ năng ngẫu nhiên.
        $skills = Skill::inRandomOrder()->take(3)->get();

        // Kiểm tra xem có đủ kỹ năng hay không.
        if ($skills->count() < 3) {
            $this->command->info('Cần ít nhất 3 kỹ năng để tạo người dùng với kỹ năng.');
            return;
        }

        // Tạo một người dùng mới.
        $user = User::create([
            'id' => Str::uuid(), // Tạo UUID
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '1234567890',
            'address' => 'Test Address',
            'password' => Hash::make('Aa_11111'), // Băm mật khẩu
            'role' => 'user',
            'photo' => 'default.jpg',
            'active' => true,
            'lock' => false
        ]);

        // Gán các kỹ năng cho người dùng.
        $user->skills()->attach($skills->pluck('id')->toArray());

        $this->command->info('Người dùng đã được tạo thành công với 3 kỹ năng.');
    }
}
