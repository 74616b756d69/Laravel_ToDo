<?php

namespace Database\Factories;

use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Transition>
 */
class TransitionFactory extends Factory
{
    public function definition(): array
    {
        $to = Status::factory()->create();

        return [
            'project_id' => $to->project_id,
            'from_status_id' => null,
            'to_status_id' => $to->id,
        ];
    }
}
