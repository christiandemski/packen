<?php

namespace App\Services;

use App\Enums\AuthSource;
use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    public const VALID_DAYS = 7;

    public function invite(User $inviter, string $name, string $email, ?Trip $trip = null): Invitation
    {
        $email = MagicLinkService::normalize($email);

        Invitation::open()->where('email', $email)->delete();

        $token = Str::random(64);
        $invitation = Invitation::create([
            'email' => $email,
            'name' => trim($name),
            'invited_by' => $inviter->id,
            'trip_id' => $trip?->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        Mail::to($email)->send(new InvitationMail($invitation, $token));

        return $invitation;
    }

    public function resend(Invitation $invitation): void
    {
        $token = Str::random(64);

        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        Mail::to($invitation->email)->send(new InvitationMail($invitation, $token));
    }

    /** Legt das Konto an; null, wenn die Einladung nicht (mehr) gültig ist. */
    public function accept(string $token, string $name): ?User
    {
        return DB::transaction(function () use ($token, $name) {
            $invitation = Invitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if (! $invitation?->isPending() || User::where('email', $invitation->email)->exists()) {
                return null;
            }

            $user = User::create([
                'name' => trim($name),
                'email' => $invitation->email,
                'auth_source' => AuthSource::Local,
                'role' => Role::Friend,
                'active' => true,
            ]);

            $invitation->update(['accepted_at' => now()]);

            // Eingeladen in eine bestimmte Reise: gleich als Mitglied aufnehmen.
            if ($invitation->trip?->isGroup()) {
                $invitation->trip->addMember($user);
            }

            return $user;
        });
    }
}
