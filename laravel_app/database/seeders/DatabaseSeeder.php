<?php

namespace Database\Seeders;

use App\Enums\TagColor;
use App\Models\Subtask;
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

        $tags = collect([
            ['name' => '仕事', 'color' => TagColor::Sky],
            ['name' => 'プライベート', 'color' => TagColor::Emerald],
            ['name' => '学習', 'color' => TagColor::Violet],
            ['name' => '至急', 'color' => TagColor::Rose],
        ])->map(fn (array $attributes) => $demo->tags()->create($attributes));

        $tasks = collect()
            ->merge(Task::factory()->count(18)->for($demo)->create())
            ->merge(Task::factory()->count(3)->for($demo)->overdue()->create())
            ->merge(Task::factory()->count(6)->for($demo)->completed()->create());

        $tasks->each(function (Task $task) use ($tags) {
            // 3 分の 2 くらいのタスクにタグを 1〜2 個付ける
            if (random_int(1, 3) > 1) {
                $task->tags()->sync($tags->random(random_int(1, 2))->pluck('id'));
            }

            // 一部のタスクにサブタスクを持たせて進捗バーを確認できるようにする
            if (random_int(1, 3) === 1) {
                Subtask::factory()->count(random_int(2, 5))->for($task)->create();
            }
        });

        // 完了日を過去にばらして、推移グラフが単調にならないようにする
        Task::where('user_id', $demo->id)->whereNotNull('completed_at')->get()
            ->each(fn (Task $task) => $task->forceFill([
                'completed_at' => now()->subDays(random_int(0, 13))->subHours(random_int(0, 12)),
            ])->save());

        // 他ユーザーのタスクが混ざらないことを目視確認するための2人目
        Task::factory()->count(5)->for(User::factory())->create();
    }
}
