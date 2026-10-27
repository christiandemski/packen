<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Admins verwalten Freunde – aber weder sich selbst noch andere Admins. */
    public function manage(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $target->isAdmin() && $actor->isNot($target);
    }

    /** Admins löschen ihr Konto nicht über die Profilseite, damit die App nie ohne Admin dasteht. */
    public function deleteOwnAccount(User $actor): bool
    {
        return ! $actor->isAdmin();
    }
}
