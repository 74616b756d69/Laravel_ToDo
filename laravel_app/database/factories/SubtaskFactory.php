<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Subtask>
 */
class SubtaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->randomElement([
                '資料を集める',
                '下書きを作る',
                'レビューを依頼する',
                '修正を反映する',
                '公開する',
            ]),
            'is_done' => fake()->boolean(40),
            'position' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['is_done' => true]);
    }
}
