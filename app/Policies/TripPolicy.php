<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function view(User $user, Trip $trip): bool
    {
        return $trip->hasMember($user);
    }

    public function create(User $user): bool
    {
        return $user->active;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $trip->isOwnedBy($user);
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $trip->isOwnedBy($user);
    }
}
