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
     * 日本語のダミー文章生成は重いので、実際にありそうな文言から選ぶ。
     */
    private const TITLES = [
        'ポートフォリオのREADMEを仕上げる',
        '企業研究のメモをまとめる',
        '面接の想定質問を10個用意する',
        'Laravelのテストを追加する',
        '健康診断を予約する',
        '技術記事を1本書く',
        '家賃を振り込む',
        'デザインのラフを作る',
        '週次の振り返りを書く',
        '英単語を30個覚える',
        'request仕様のレビュー依頼を出す',
        '請求書を送付する',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement(self::TITLES),
            'content' => fake()->boolean(80)
                ? '<h2>やること</h2><p>目的と完了条件をはっきりさせてから着手する。</p>'
                    .'<ul><li>必要な資料を集める</li><li>下書きを作る</li><li>見直して仕上げる</li></ul>'
                : null,
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()->boolean(70)
                ? fake()->dateTimeBetween('-1 week', '+3 weeks')->format('Y-m-d')
                : null,
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ];
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
