<?php

namespace Database\Factories;

use App\Enums\ContainerKind;
use App\Models\Container;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'kind' => fake()->randomElement(ContainerKind::cases()),
        ];
    }
}
