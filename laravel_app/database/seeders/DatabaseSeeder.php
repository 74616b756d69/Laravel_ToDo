<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ログインしてすぐ全機能を確認できるデモアカウント
        $this->call(DemoUserSeeder::class);

        // データがユーザーごとに分離されていることを確認するための2人目
        $other = User::updateOrCreate(
            ['email' => 'other@example.com'],
            ['name' => '別のユーザー', 'password' => 'password123'],
        );

        if ($other->tasks()->doesntExist()) {
            Task::factory()->count(12)->for($other)->create();
        }
    }
}
