<?php

namespace Database\Factories;

use App\Enums\SprintState;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Sprint>
 */
class SprintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->lexify('Sprint ???'),
            // realText() は日本語コーパスを丸ごと読み込んで重いので使わない
            'goal' => fake()->optional()->randomElement([
                '主要な導線を一通り使えるようにする。',
                '積み残しを片付けて見通しをよくする。',
                'バグ報告への対応を優先する。',
            ]),
            'start_date' => null,
            'end_date' => null,
            'state' => SprintState::Future,
            'active_marker' => null,
        ];
    }

    /**
     * 進行中のスプリント。プロジェクトごとに 1 つしか作れない点に注意。
     */
    public function active(?string $start = null, ?string $end = null): static
    {
        return $this->state(fn () => [
            'state' => SprintState::Active,
            'active_marker' => 1,
            'start_date' => $start ?? today()->subDays(5),
            'end_date' => $end ?? today()->addDays(5),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'state' => SprintState::Closed,
            'active_marker' => null,
            'start_date' => today()->subDays(20),
            'end_date' => today()->subDays(6),
        ]);
    }
}
