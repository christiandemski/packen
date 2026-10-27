<?php

namespace Database\Factories;

use App\Enums\AuthSource;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'auth_source' => AuthSource::Local,
            'role' => Role::Friend,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => Role::Admin,
            'password' => 'geheim-passwort',
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
