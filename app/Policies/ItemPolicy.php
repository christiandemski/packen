<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function view(User $user, Item $item): bool
    {
        return $item->isAccessibleBy($user);
    }

    public function update(User $user, Item $item): bool
    {
        return $item->isAccessibleBy($user);
    }

    public function delete(User $user, Item $item): bool
    {
        return $item->isAccessibleBy($user);
    }
}
