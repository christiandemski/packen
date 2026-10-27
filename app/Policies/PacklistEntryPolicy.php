<?php

namespace App\Policies;

use App\Models\PacklistEntry;
use App\Models\User;

/** Persönliche Packliste: nur die eigene (Freigaben für Mitreisende folgen in Meilenstein 5). */
class PacklistEntryPolicy
{
    public function update(User $user, PacklistEntry $entry): bool
    {
        return $entry->user_id === $user->id && $entry->trip->hasMember($user);
    }

    public function delete(User $user, PacklistEntry $entry): bool
    {
        return $this->update($user, $entry);
    }
}
