<?php

namespace Database\Factories;

use App\Enums\Accommodation;
use App\Enums\Transport;
use App\Enums\TripMemberRole;
use App\Enums\TripMode;
use App\Enums\TripType;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'owner_id' => User::factory(),
            'name' => fake()->city(),
            'location' => fake()->city(),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+4 days'),
            'type' => fake()->randomElement(TripType::cases()),
            'mode' => TripMode::Solo,
            'accommodation' => fake()->randomElement(Accommodation::cases()),
            'transport' => fake()->randomElement(Transport::cases()),
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => ['mode' => TripMode::Group]);
    }

    /** Owner wird automatisch Mitglied – wie beim Anlegen über den TripService. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Trip $trip) => $trip->addMember($trip->owner, TripMemberRole::Owner));
    }
}
