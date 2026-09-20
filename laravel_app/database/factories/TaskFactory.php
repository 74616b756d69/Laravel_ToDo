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
    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'user_id' => User::factory(),
            'title' => fake()->realText(24),
            'content' => fake()->boolean(80) ? fake()->realText(120) : null,
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
