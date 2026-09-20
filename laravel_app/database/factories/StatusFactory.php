<?php

namespace Database\Factories;

use App\Enums\StatusCategory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Status>
 */
class StatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->lexify('状態???'),
            'category' => StatusCategory::Todo,
            'position' => 0,
        ];
    }

    public function category(StatusCategory $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }
}
