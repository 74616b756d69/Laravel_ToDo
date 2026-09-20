<?php

namespace Database\Factories;

use App\Enums\TagColor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->lexify('タグ???'),
            'color' => fake()->randomElement(TagColor::cases()),
        ];
    }
}
