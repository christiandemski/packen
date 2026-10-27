<?php

namespace App\Services;

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkService
{
    public const VALID_MINUTES = 15;

    /**
     * Verschickt einen Anmeldelink, falls ein aktives Konto zur Adresse existiert.
     * Der Aufrufer erfährt bewusst nicht, ob das der Fall war; die Mail geht erst
     * nach der Antwort raus, damit auch die Antwortzeit nichts verrät.
     */
    public function send(string $email, ?string $ip): void
    {
        $user = User::where('email', self::normalize($email))->first();

        if ($user === null || ! $user->active) {
            return;
        }

        $token = Str::random(64);

        $user->loginTokens()->whereNull('used_at')->delete();
        $user->loginTokens()->create([
            'token_hash' => self::hash($token),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
            'requested_ip' => $ip,
        ]);

        defer(fn () => Mail::to($user)->send(new MagicLinkMail($user, $token)));
    }

    /** Liefert den Token, wenn er noch einlösbar ist (ohne ihn zu verbrauchen). */
    public function find(string $token): ?LoginToken
    {
        $loginToken = LoginToken::with('user')->where('token_hash', self::hash($token))->first();

        return $loginToken?->isUsable() && $loginToken->user->active ? $loginToken : null;
    }

    /** Löst den Token genau einmal ein; parallele Versuche scheitern am bedingten Update. */
    public function consume(string $token): ?User
    {
        $hash = self::hash($token);

        $claimed = LoginToken::where('token_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($claimed !== 1) {
            return null;
        }

        $user = LoginToken::where('token_hash', $hash)->first()?->user;

        return $user?->active ? $user : null;
    }

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
