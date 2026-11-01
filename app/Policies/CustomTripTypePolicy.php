<?php

namespace App\Policies;

use App\Models\CustomTripType;
use App\Models\User;

class CustomTripTypePolicy
{
    public function update(User $user, CustomTripType $type): bool
    {
        return $type->isAccessibleBy($user);
    }

    public function delete(User $user, CustomTripType $type): bool
    {
        return $type->isAccessibleBy($user);
    }
}
