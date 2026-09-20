<?php

namespace Database\Seeders;

use App\Enums\TagColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ログインしてすぐ中身を確認できるデモアカウントを作る。
 *
 * ダッシュボードの各指標（推移グラフ・完了率・連続達成日数・優先度の内訳）が
 * それぞれ意味のある見え方になるよう、ランダム任せにせず件数を設計している。
 */
class DemoUserSeeder extends Seeder
{
    /** 作成するタスクの総数 */
    private const TASK_COUNT = 100;

    /** 推移グラフの日数 */
    private const TREND_DAYS = 14;

    /** 今日から遡って何日連続で完了させるか（連続達成日数の見せ場） */
    private const STREAK_DAYS = 5;

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => config('demo.email')],
            [
                'name' => 'デモユーザー',
                'password' => config('demo.password'),
                'email_verified_at' => now(),
            ],
        );

        $tags = $this->createTags($user);

        // すでにデータがある場合は積み増さない（再実行しても件数と内訳を保つ）
        if ($user->tasks()->exists()) {
            $this->command?->info('デモアカウントは作成済みのため、タスクの生成をスキップしました。');

            return;
        }

        $completed = $this->createCompletedTasks($user);
        $open = $this->createOpenTasks($user);

        $tasks = $completed->merge($open);

        $this->attachTags($tasks, $tags);
        $this->createSubtasks($tasks);

        $this->command?->info(sprintf(
            'デモアカウント（%s / %s）にタスク %d 件を作成しました。',
            config('demo.email'),
            config('demo.password'),
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
        ])->map(fn (array $attributes) => $user->tags()->firstOrCreate(
            ['name' => $attributes['name']],
            ['color' => $attributes['color']],
        ));
    }

    /**
     * 完了済みタスク。完了日を日ごとに割り当て、推移グラフに起伏を作る。
     *
     * @return Collection<int, Task>
     */
    private function createCompletedTasks(User $user): Collection
    {
        return $this->completionPlan()
            ->flatMap(fn (int $count, int $daysAgo) => collect(range(1, $count))->map(
                fn () => $this->completedTask($user, today()->subDays($daysAgo)),
            ))
            ->values();
    }

    /**
     * 「何日前に何件完了したか」の計画表。
     * 直近 STREAK_DAYS 日は必ず 1 件以上入れて連続達成が途切れないようにする。
     *
     * @return Collection<int, int> キー: 何日前 / 値: 件数
     */
    private function completionPlan(): Collection
    {
        return collect(range(0, self::TREND_DAYS - 1))
            ->mapWithKeys(fn (int $daysAgo) => [
                $daysAgo => $daysAgo < self::STREAK_DAYS
                    ? random_int(1, 4)          // 連続達成中の期間
                    : random_int(0, 5),         // それ以前は 0 件の日があってよい
            ]);
    }

    private function completedTask(User $user, Carbon $completedOn): Task
    {
        $completedAt = $completedOn->copy()->setTime(random_int(9, 21), random_int(0, 59));

        return Task::factory()->for($user)->create([
            'status' => TaskStatus::Done,
            'completed_at' => $completedAt,
            // 期限は完了日の前後に置く（完了済みは期限切れに数えない挙動の確認用）
            'due_date' => random_int(1, 4) === 1 ? null : $completedOn->copy()->addDays(random_int(-2, 3)),
            'created_at' => $completedAt->copy()->subDays(random_int(1, 20)),
        ]);
    }

    /**
     * 未完了タスク。優先度の内訳が偏らないよう配分を決めて作る。
     *
     * @return Collection<int, Task>
     */
    private function createOpenTasks(User $user): Collection
    {
        $remaining = max(self::TASK_COUNT - Task::where('user_id', $user->id)->count(), 0);

        // 高:中:低 = 2:3:3、そのうち一部を期限切れにする
        $plan = [
            [TaskPriority::High, (int) round($remaining * 0.25)],
            [TaskPriority::Medium, (int) round($remaining * 0.40)],
            [TaskPriority::Low, 0],
        ];
        $plan[2][1] = $remaining - $plan[0][1] - $plan[1][1];

        return collect($plan)->flatMap(fn (array $row) => Task::factory()
            ->count($row[1])
            ->for($user)
            ->create([
                'priority' => $row[0],
                'status' => fn () => fake()->randomElement([TaskStatus::Todo, TaskStatus::Doing]),
                'completed_at' => null,
                'due_date' => fn () => $this->openDueDate(),
            ]))->values();
    }

    /**
     * 未完了タスクの期限。「期限切れ」「期限間近」「余裕あり」「未設定」を混ぜる。
     */
    private function openDueDate(): ?string
    {
        return match (random_int(1, 10)) {
            1, 2 => today()->subDays(random_int(1, 10))->toDateString(),  // 期限切れ
            3, 4, 5 => today()->addDays(random_int(0, 2))->toDateString(), // 期限間近
            6, 7, 8 => today()->addDays(random_int(3, 21))->toDateString(),
            default => null,
        };
    }

    /**
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
                collect(range(1, random_int(2, 5)))->each(fn (int $position) => Subtask::factory()
                    ->for($task)
                    ->create([
                        'position' => $position,
                        // 完了済みのタスクはサブタスクもすべて完了させる
                        'is_done' => $task->status === TaskStatus::Done || random_int(1, 3) === 1,
                    ]));
            });
    }
}
