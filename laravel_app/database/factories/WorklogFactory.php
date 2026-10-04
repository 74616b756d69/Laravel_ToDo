<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Worklog>
 */
class WorklogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'minutes' => fake()->randomElement([30, 60, 90, 120, 180]),
            'worked_on' => today(),
            'comment' => null,
        ];
    }
}
