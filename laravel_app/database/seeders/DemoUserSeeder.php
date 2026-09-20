<?php

namespace Database\Seeders;

use App\Enums\IssueLinkType;
use App\Enums\IssueType;
use App\Enums\StatusCategory;
use App\Enums\TagColor;
use App\Enums\TaskPriority;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use App\Services\IssueLinkService;
use App\Services\SprintService;
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

        // 課題はプロジェクトに属するので、デモ用の個人プロジェクトを先に用意する
        $project = Project::personalFor($user);

        // すでにデータがある場合は積み増さない（再実行しても件数と内訳を保つ）
        if ($project->issues()->exists()) {
            $this->command?->info('デモアカウントは作成済みのため、タスクの生成をスキップしました。');

            return;
        }

        $completed = $this->createCompletedTasks($project, $user);
        $open = $this->createOpenTasks($project, $user);

        $tasks = $completed->merge($open);

        $this->attachTags($tasks, $tags);
        $this->createChildIssues($project, $tasks);
        $this->createSprints($project, $open);
        $this->createComments($tasks);
        $this->createLinks($project);

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
     * @return Collection<int, Issue>
     */
    private function createCompletedTasks(Project $project, User $user): Collection
    {
        return $this->completionPlan()
            ->flatMap(fn (int $count, int $daysAgo) => collect(range(1, $count))->map(
                fn () => $this->completedTask($project, $user, today()->subDays($daysAgo)),
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

    private function completedTask(Project $project, User $user, Carbon $completedOn): Issue
    {
        $completedAt = $completedOn->copy()->setTime(random_int(9, 21), random_int(0, 59));

        return Issue::factory()->inProject($project, $user)->create([
            'status_id' => $project->doneStatus()->id,
            'completed_at' => $completedAt,
            // 期限は完了日の前後に置く（完了済みは期限切れに数えない挙動の確認用）
            'due_date' => random_int(1, 4) === 1 ? null : $completedOn->copy()->addDays(random_int(-2, 3)),
            'created_at' => $completedAt->copy()->subDays(random_int(1, 20)),
        ]);
    }

    /**
     * 未完了タスク。優先度の内訳が偏らないよう配分を決めて作る。
     *
     * @return Collection<int, Issue>
     */
    private function createOpenTasks(Project $project, User $user): Collection
    {
        $remaining = max(self::TASK_COUNT - $project->issues()->count(), 0);

        // 高:中:低 = 2:3:3、そのうち一部を期限切れにする
        $plan = [
            [TaskPriority::High, (int) round($remaining * 0.25)],
            [TaskPriority::Medium, (int) round($remaining * 0.40)],
            [TaskPriority::Low, 0],
        ];
        $plan[2][1] = $remaining - $plan[0][1] - $plan[1][1];

        return collect($plan)->flatMap(fn (array $row) => Issue::factory()
            ->count($row[1])
            ->inProject($project, $user)
            ->create([
                'priority' => $row[0],
                // 未完了レーン（To Do / In Progress / In Review）に散らす
                'status_id' => fn () => $this->openStatuses($project)->random()->id,
                'completed_at' => null,
                'due_date' => fn () => $this->openDueDate(),
            ]))->values();
    }

    /**
     * 一部の課題に会話を置いて、コメントタブが空でない状態にする。
     *
     * @param  Collection<int, Issue>  $tasks
     */
    private function createComments(Collection $tasks): void
    {
        $author = User::where('email', config('demo.email'))->firstOrFail();

        $tasks->filter(fn () => random_int(1, 5) === 1)
            ->each(fn (Issue $issue) => Comment::factory()
                ->count(random_int(1, 3))
                ->for($issue)
                ->for($author)
                ->create());
    }

    /**
     * いくつかの課題を関連づけて、「リンクされた作業項目」が空でない状態にする。
     */
    private function createLinks(Project $project): void
    {
        $links = app(IssueLinkService::class);

        $candidates = $project->issues()->whereNull('parent_id')->inRandomOrder()->limit(12)->get();

        // chunk() はキーを保ったままなので、values() で振り直さないと
        // 2 組目以降の get(0) が null になる
        $candidates->chunk(3)->each(function (Collection $group) use ($links) {
            $group = $group->values();

            [$source, $related, $blocked] = [$group->get(0), $group->get(1), $group->get(2)];

            if ($source === null) {
                return;
            }

            if ($related !== null) {
                $links->link($source, $related, IssueLinkType::Relates);
            }

            if ($blocked !== null) {
                $links->link($source, $blocked, IssueLinkType::Blocks);
            }
        });
    }

    /**
     * デモ用のスプリント。
     *
     * 進行中スプリントを 1 つ作ってバーンダウンが描ける状態にし、
     * 次のスプリントも 1 つ用意して「移送先が選べる」ことを見せる。
     *
     * @param  Collection<int, Issue>  $open
     */
    private function createSprints(Project $project, Collection $open): void
    {
        $sprints = app(SprintService::class);

        $closed = $sprints->create($project, [
            'name' => 'Sprint 1',
            'goal' => '認証まわりを片付ける',
            'start_date' => today()->subDays(24),
            'end_date' => today()->subDays(11),
        ]);
        $closed->forceFill(['state' => \App\Enums\SprintState::Closed])->save();

        $current = $sprints->create($project, [
            'name' => 'Sprint 2',
            'goal' => 'ボードとバックログを使えるところまで持っていく',
            'start_date' => today()->subDays(5),
            'end_date' => today()->addDays(8),
        ]);
        $sprints->start($current);

        $sprints->create($project, [
            'name' => 'Sprint 3',
            'goal' => '通知とレポート',
            'start_date' => today()->addDays(9),
            'end_date' => today()->addDays(22),
        ]);

        // 進行中スプリントに未完了と完了済みを混ぜ、バーンダウンに起伏を作る
        $planned = $open->take(14);
        $burned = $this->burnedIssues($project, $current);

        Issue::whereIn('id', $planned->pluck('id'))->update(['sprint_id' => $current->id]);

        $planned->merge($burned)->each(fn (Issue $issue) => $issue->forceFill([
            'story_points' => fake()->randomElement([1, 2, 3, 5, 8]),
        ])->save());
    }

    /**
     * 進行中スプリントの「すでに終わった分」。日ごとにばらして線を階段状にする。
     *
     * @return Collection<int, Issue>
     */
    private function burnedIssues(Project $project, Sprint $sprint): Collection
    {
        $done = $project->issues()
            ->completed()
            ->whereNull('parent_id')
            ->limit(8)
            ->get();

        $span = max($done->count() - 1, 1);

        $done->each(fn (Issue $issue, int $index) => $issue->forceFill([
            'sprint_id' => $sprint->id,
            // 5 日前から今日にかけて順に完了したことにして、線を階段状にする
            'completed_at' => today()->subDays(5)
                ->addDays(intdiv($index * 5, $span))
                ->setTime(random_int(10, 19), random_int(0, 59)),
        ])->save());

        return $done;
    }

    /**
     * 未完了として使えるステータス（完了カテゴリ以外）。
     *
     * @return \Illuminate\Support\Collection<int, Status>
     */
    private function openStatuses(Project $project)
    {
        return $project->statuses()
            ->where('category', '!=', StatusCategory::Done)
            ->get();
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
     * @param  Collection<int, Issue>  $tasks
     * @param  Collection<int, Tag>  $tags
     */
    private function attachTags(Collection $tasks, Collection $tags): void
    {
        $rows = $tasks
            ->filter(fn () => random_int(1, 3) > 1)
            ->flatMap(fn (Issue $task) => $tags
                ->random(random_int(1, 2))
                ->map(fn (Tag $tag) => ['task_id' => $task->id, 'tag_id' => $tag->id]))
            ->all();

        // 1 件ずつ attach すると件数分のクエリが飛ぶので、中間テーブルへまとめて流し込む
        collect($rows)->chunk(200)->each(
            fn (Collection $chunk) => DB::table('tag_task')->insert($chunk->values()->all()),
        );
    }

    /**
     * 4 分の 1 くらいの課題に子課題を持たせ、進捗バーを確認できるようにする。
     *
     * @param  Collection<int, Issue>  $tasks
     */
    private function createChildIssues(Project $project, Collection $tasks): void
    {
        $tasks
            ->filter(fn () => random_int(1, 4) === 1)
            ->each(function (Issue $task) use ($project) {
                collect(range(1, random_int(2, 5)))->each(function (int $position) use ($project, $task) {
                    // 完了済みの親は子もすべて完了させる
                    $done = $task->isCompleted() || random_int(1, 3) === 1;

                    $project->createIssue([
                        'title' => fake()->randomElement([
                            '資料を集める', '下書きを作る', 'レビューを依頼する', '修正を反映する', '公開する',
                        ]),
                        'issue_type' => IssueType::Subtask,
                        'parent_id' => $task->id,
                        'status_id' => $done
                            ? $project->doneStatus()->id
                            : $project->initialStatus()->id,
                        'priority' => TaskPriority::Medium,
                        'reporter_id' => $task->reporter_id,
                        'assignee_id' => $task->assignee_id,
                        'position' => $position,
                        'completed_at' => $done ? now() : null,
                    ]);
                });
            });
    }
}
