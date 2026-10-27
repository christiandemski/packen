<?php

namespace App\Services;

use App\Enums\TripMemberRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Sperren, Entsperren und Löschen von Konten. */
class AccountService
{
    public function __construct(private readonly TripService $trips) {}

    public function block(User $user): void
    {
        $user->forceFill([
            'active' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $this->endSessions($user);
        $user->loginTokens()->delete();
    }

    public function unblock(User $user): void
    {
        $user->forceFill(['active' => true])->save();
    }

    /**
     * Löscht das Konto samt eigenen Daten. Gemeinsame Reisen, die der Person gehören,
     * gehen an das am längsten dabei befindliche Mitglied über; ohne Mitreisende wird
     * die Reise gelöscht.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach ($user->ownedTrips()->get() as $trip) {
                $successor = $trip->members()
                    ->whereKeyNot($user->id)
                    ->orderBy('trip_members.joined_at')
                    ->first();

                if ($successor === null) {
                    $this->trips->delete($trip);

                    continue;
                }

                $trip->update(['owner_id' => $successor->id]);
                $trip->members()->updateExistingPivot($successor->id, ['role' => TripMemberRole::Owner->value]);
            }

            $this->endSessions($user);
            Invitation::where('email', $user->email)->delete();

            $user->delete();
        });
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
