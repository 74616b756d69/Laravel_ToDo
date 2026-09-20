<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
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

    private const SUBTASK_TITLES = [
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
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(self::SUBJECTS).fake()->randomElement(self::ACTIONS),
            'content' => fake()->boolean(80) ? $this->richContent() : null,
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->boolean(70)
                ? fake()->dateTimeBetween('-1 week', '+3 weeks')->format('Y-m-d')
                : null,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ];
    }

    /**
     * リッチテキストの表示を確認できるよう、見出し・リスト・チェックリストを混ぜた本文を作る。
     */
    private function richContent(): string
    {
        $items = fake()->randomElements(self::SUBTASK_TITLES, 3);

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
        return $this->state(fn () => [
            'status' => TaskStatus::Done,
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Todo,
            'completed_at' => null,
            'due_date' => today()->subDays(3),
        ]);
    }
}
