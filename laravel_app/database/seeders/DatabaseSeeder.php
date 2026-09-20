<?php

namespace Database\Seeders;

use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ログインしてすぐ全機能を確認できるデモアカウント
        $this->call(DemoUserSeeder::class);

        // データがプロジェクトごとに分離されていることを確認するための2人目
        $other = User::updateOrCreate(
            ['email' => 'other@example.com'],
            ['name' => '別のユーザー', 'password' => 'password123'],
        );

        $project = Project::personalFor($other);

        if ($project->issues()->doesntExist()) {
            Issue::factory()->count(12)->inProject($project, $other)->create();
        }
    }
}
