<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    private const BODIES = [
        '<p>対応方針はこれで問題なさそうです。</p>',
        '<p>再現手順を追記しました。確認お願いします。</p>',
        '<p>レビューしました。細かい指摘を 2 点だけ。</p>',
        '<p>先に別の課題を片付けてから着手します。</p>',
        '<p>仕様を確認したので、このまま進めます。</p>',
    ];

    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'body' => fake()->randomElement(self::BODIES),
        ];
    }

    public function edited(): static
    {
        return $this->state(fn () => ['edited_at' => now()]);
    }
}
