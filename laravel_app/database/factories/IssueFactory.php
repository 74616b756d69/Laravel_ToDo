<?php

namespace Database\Factories;

use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\StatusCategory;
use App\Enums\TaskPriority;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    /**
     * 日本語のダミー文章生成は重いので、実際にありそうな文言を組み合わせて作る。
     * 「対象 × 動作」の掛け合わせにして、件数を増やしても同じ文言が並びにくいようにしている。
     */
    private const SUBJECTS = [
        'ポートフォリオのREADME',
        '企業研究のメモ',
        '面接の想定質問',
        'Laravelのテストコード',
        '技術記事の下書き',
        '職務経歴書',
        'データベースの設計書',
        'API仕様書',
        'デザインのラフ',
        '週次の振り返り',
        '英単語リスト',
        '家計簿',
        '読書メモ',
        '勉強会の資料',
        'タスク管理アプリの改善案',
        'クラウド構成図',
        'リリースノート',
        '請求書',
        '健康診断の予約',
        '旅行の日程表',
    ];

    private const ACTIONS = [
        'を仕上げる',
        'をまとめる',
        'を見直す',
        'を作成する',
        'のレビューを依頼する',
        'を更新する',
        'を共有する',
        'に手を付ける',
    ];

    private const CHILD_TITLES = [
        '必要な資料を集める',
        '構成を決める',
        '下書きを作る',
        'レビューを依頼する',
        '指摘を反映する',
        '最終チェックをする',
        '公開する',
    ];

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            // issue_number は入れない。Issue の creating フックが
            // プロジェクトのカウンタから払い出す（count() での重複を避けるため）
            'issue_type' => IssueType::Task,
            'parent_id' => null,
            'reporter_id' => User::factory(),
            'assignee_id' => fn (array $attributes) => $attributes['reporter_id'],
            'title' => fake()->randomElement(self::SUBJECTS).fake()->randomElement(self::ACTIONS),
            'content' => fake()->boolean(80) ? $this->richContent() : null,
            // project_id が解決されたあとに評価されるので、そのプロジェクトの
            // ワークフローから 1 つ選べる
            'status_id' => fn (array $attributes) => $this->randomStatus((int) $attributes['project_id'])->id,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'position' => 0,
            'due_date' => fake()->boolean(70)
                ? fake()->dateTimeBetween('-1 week', '+3 weeks')->format('Y-m-d')
                : null,
            'completed_at' => fn (array $attributes) => Status::find($attributes['status_id'])?->isDone()
                ? now()
                : null,
        ];
    }

    /**
     * そのプロジェクトのワークフローからランダムに 1 つ選ぶ。
     */
    private function randomStatus(int $projectId): Status
    {
        return Status::where('project_id', $projectId)->get()->random();
    }

    /**
     * 指定したステータスに置く。
     */
    public function inStatus(Status $status): static
    {
        return $this->state(fn () => [
            'project_id' => $status->project_id,
            'status_id' => $status->id,
            'completed_at' => $status->isDone() ? now() : null,
        ]);
    }

    /**
     * 指定カテゴリのステータスに置く。プロジェクトを跨いで使える。
     */
    public function inCategory(StatusCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => Status::where('project_id', $attributes['project_id'])
                ->where('category', $category)
                ->firstOrFail()
                ->id,
            'completed_at' => $category->isDone() ? now() : null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Issue $issue) {
            // 起票者がプロジェクトのメンバーでないと、作った本人から見えない課題になる
            $isMember = $issue->project->members()
                ->where('user_id', $issue->reporter_id)
                ->exists();

            if (! $isMember) {
                $issue->project->members()->create([
                    'user_id' => $issue->reporter_id,
                    'role' => ProjectRole::Admin,
                ]);
            }
        });
    }

    /**
     * そのユーザーの個人プロジェクトに属する課題。
     * 移行前の `Task::factory()->for($user)` の置き換え。
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'project_id' => Project::personalFor($user)->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
        ]);
    }

    /**
     * 指定プロジェクトに属する課題。起票者は自動でメンバーに加えない。
     */
    public function inProject(Project $project, ?User $reporter = null): static
    {
        return $this->state(fn () => array_filter([
            'project_id' => $project->id,
            'reporter_id' => $reporter?->id,
            'assignee_id' => $reporter?->id,
        ]));
    }

    /**
     * 親を持つサブタスク。
     */
    public function childOf(Issue $parent): static
    {
        return $this->state(fn () => [
            'project_id' => $parent->project_id,
            'parent_id' => $parent->id,
            'issue_type' => IssueType::Subtask,
            'reporter_id' => $parent->reporter_id,
            'assignee_id' => $parent->assignee_id,
            'title' => fake()->randomElement(self::CHILD_TITLES),
            'content' => null,
        ]);
    }

    /**
     * リッチテキストの表示を確認できるよう、見出し・リスト・チェックリストを混ぜた本文を作る。
     */
    private function richContent(): string
    {
        $items = fake()->randomElements(self::CHILD_TITLES, 3);

        $body = '<h2>やること</h2>'
            .'<p>目的と完了条件をはっきりさせてから着手する。</p>'
            .'<ul><li>'.implode('</li><li>', $items).'</li></ul>';

        // 一部はチェックリストと引用も含めて、エディタの表現力がわかるようにする
        if (fake()->boolean(35)) {
            $body .= '<ul data-type="taskList">'
                .'<li data-checked="true" data-type="taskItem"><label><input type="checkbox" checked="checked"><span></span></label><div><p>関連資料に目を通す</p></div></li>'
                .'<li data-checked="false" data-type="taskItem"><label><input type="checkbox"><span></span></label><div><p>担当者に確認する</p></div></li>'
                .'</ul>';
        }

        if (fake()->boolean(25)) {
            $body .= '<blockquote><p>迷ったら小さく分割して、1つずつ片付ける。</p></blockquote>';
        }

        return $body;
    }

    public function completed(): static
    {
        return $this->inCategory(StatusCategory::Done);
    }

    public function overdue(): static
    {
        return $this->inCategory(StatusCategory::Todo)->state(fn () => [
            'completed_at' => null,
            'due_date' => today()->subDays(3),
        ]);
    }

    public function done(): static
    {
        return $this->completed();
    }
}
