<?php

namespace Database\Factories;

use App\Enums\ActivityField;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'user_id' => User::factory(),
            'field' => ActivityField::Priority,
            'old_value' => '中',
            'new_value' => '高',
        ];
    }
}
