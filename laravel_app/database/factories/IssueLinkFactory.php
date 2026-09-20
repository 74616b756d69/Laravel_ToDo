<?php

namespace Database\Factories;

use App\Enums\IssueLinkType;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\IssueLink>
 */
class IssueLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_issue_id' => Issue::factory(),
            'target_issue_id' => Issue::factory(),
            'type' => IssueLinkType::Relates,
        ];
    }
}
