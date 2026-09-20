<?php

namespace Database\Seeders;

use App\Enums\TagColor;
use App\Enums\TaskStatus;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /** デモユーザーに作るタスクの件数 */
    private const TASK_COUNT = 100;

    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'デモユーザー',
            'email' => 'demo@example.com',
            'password' => 'password123',
        ]);

        $tags = $this->createTags($demo);
        $tasks = $this->createTasks($demo);

        $this->attachTags($tasks, $tags);
        $this->createSubtasks($tasks);
        $this->spreadCompletionDates($tasks);

        // 他ユーザーのタスクが混ざらないことを確認するための2人目
        $other = User::factory()->create(['name' => '別のユーザー', 'email' => 'other@example.com']);
        Task::factory()->count(12)->for($other)->create();

        $this->command?->info(sprintf(
            'デモユーザー（demo@example.com / password123）に %d 件のタスクを作成しました。',
            $tasks->count(),
        ));
    }

    /** @return Collection<int, Tag> */
    private function createTags(User $user): Collection
    {
        return collect([
            ['name' => '仕事', 'color' => TagColor::Sky],
            ['name' => 'プライベート', 'color' => TagColor::Emerald],
            ['name' => '学習', 'color' => TagColor::Violet],
            ['name' => '至急', 'color' => TagColor::Rose],
            ['name' => '事務手続き', 'color' => TagColor::Amber],
            ['name' => 'あとで読む', 'color' => TagColor::Slate],
        ])->map(fn (array $attributes) => $user->tags()->create($attributes));
    }

    /**
     * 画面の見え方が偏らないよう、状態の内訳を決めてから作る。
     *
     * @return Collection<int, Task>
     */
    private function createTasks(User $user): Collection
    {
        $overdue = (int) round(self::TASK_COUNT * 0.08);   // 期限切れ
        $completed = (int) round(self::TASK_COUNT * 0.35); // 完了済み
        $rest = self::TASK_COUNT - $overdue - $completed;  // 未着手・進行中が混ざる

        return collect()
            ->merge(Task::factory()->count($overdue)->for($user)->overdue()->create())
            ->merge(Task::factory()->count($completed)->for($user)->completed()->create())
            ->merge(Task::factory()->count($rest)->for($user)->create([
                // 残りは未完了だけにする。クロージャで渡さないと全件が同じ値になる
                'status' => fn () => fake()->randomElement([TaskStatus::Todo, TaskStatus::Doing]),
                'completed_at' => null,
            ]));
    }

    /**
     * 3 分の 2 くらいのタスクにタグを 1〜2 個付ける。
     *
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Tag>  $tags
     */
    private function attachTags(Collection $tasks, Collection $tags): void
    {
        $rows = $tasks
            ->filter(fn () => random_int(1, 3) > 1)
            ->flatMap(fn (Task $task) => $tags
                ->random(random_int(1, 2))
                ->map(fn (Tag $tag) => ['task_id' => $task->id, 'tag_id' => $tag->id]))
            ->all();

        // 1 件ずつ attach すると件数分のクエリが飛ぶので、中間テーブルへまとめて流し込む
        collect($rows)->chunk(200)->each(
            fn (Collection $chunk) => DB::table('tag_task')->insert($chunk->values()->all()),
        );
    }

    /**
     * 4 分の 1 くらいのタスクにサブタスクを持たせ、進捗バーを確認できるようにする。
     *
     * @param  Collection<int, Task>  $tasks
     */
    private function createSubtasks(Collection $tasks): void
    {
        $tasks
            ->filter(fn () => random_int(1, 4) === 1)
            ->each(function (Task $task) {
                $count = random_int(2, 5);

                collect(range(1, $count))->each(fn (int $position) => Subtask::factory()
                    ->for($task)
                    ->create([
                        'position' => $position,
                        // 完了済みのタスクはサブタスクもすべて完了させる
                        'is_done' => $task->status === TaskStatus::Done || random_int(1, 3) === 1,
                    ]));
            });
    }

    /**
     * 完了日を過去 2 週間に散らして、ダッシュボードの推移グラフが単調にならないようにする。
     *
     * @param  Collection<int, Task>  $tasks
     */
    private function spreadCompletionDates(Collection $tasks): void
    {
        $tasks
            ->where('status', TaskStatus::Done)
            ->each(fn (Task $task) => $task->forceFill([
                'completed_at' => now()
                    ->subDays(random_int(0, 13))
                    ->setTime(random_int(9, 21), random_int(0, 59)),
            ])->save());
    }
}
