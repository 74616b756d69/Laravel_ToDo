<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 動作確認用のデモアカウント
        $demo = User::factory()->create([
            'name' => 'デモユーザー',
            'email' => 'demo@example.com',
            'password' => 'password123',
        ]);

        Task::factory()->count(18)->for($demo)->create();
        Task::factory()->count(3)->for($demo)->overdue()->create();
        Task::factory()->count(4)->for($demo)->completed()->create();

        // 他ユーザーのタスクが混ざらないことを目視確認するための2人目
        Task::factory()->count(5)->for(User::factory())->create();
    }
}
