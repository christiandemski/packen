<?php

namespace Database\Factories;

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            // Aufgaben („Herd aus“) verhalten sich anders (kein Gewicht, kein Behältnis) – nur ausdrücklich
            'category' => fake()->randomElement(array_filter(ItemCategory::cases(), fn (ItemCategory $c) => ! $c->isTask())),
            'weight_g' => fake()->optional()->numberBetween(10, 2000),
        ];
    }
}
