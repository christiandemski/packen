<?php

namespace App\Policies;

use App\Models\PacklistEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Persönliche Packliste: Bearbeiten nur durch die Person selbst. Abhaken zusätzlich
 * durch Mitreisende, denen sie ihre Liste freigegeben hat („für jemanden mitpacken“).
 */
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

    public function toggle(User $user, PacklistEntry $entry): bool
    {
        return $this->update($user, $entry) || (
            $entry->trip->hasMember($user)
            && self::hasGrant($entry->trip_id, $entry->user_id, $user->id)
        );
    }

    public static function hasGrant(int $tripId, int $ownerId, int $granteeId): bool
    {
        return DB::table('packlist_grants')->where(['trip_id' => $tripId, 'owner_id' => $ownerId, 'grantee_id' => $granteeId])->exists();
    }
}
