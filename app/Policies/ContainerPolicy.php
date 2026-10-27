<?php

namespace App\Policies;

use App\Models\Container;
use App\Models\User;

class ContainerPolicy
{
    public function update(User $user, Container $container): bool
    {
        return $container->isAccessibleBy($user);
    }

    public function delete(User $user, Container $container): bool
    {
        return $container->isAccessibleBy($user);
    }
}
