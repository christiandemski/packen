<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Zugänge für andere Apps (z. B. „unterwegs“). Der Klartext-Token wird genau einmal
 * angezeigt und nur als SHA-256-Hash gespeichert – wie bei den Magic Links.
 */
class ApiTokenService
{
    public const PREFIX = 'packen_';

    /** @return array{ApiToken, string} Token-Datensatz und Klartext */
    public function create(User $user, string $name): array
    {
        $plain = self::PREFIX.Str::random(48);

        $token = $user->apiTokens()->create([
            'name' => trim($name),
            'token_hash' => self::hash($plain),
        ]);

        return [$token, $plain];
    }

    /** Gültiger Token eines aktiven Kontos, sonst null. Merkt sich die letzte Nutzung (höchstens minütlich). */
    public function authenticate(?string $plain): ?ApiToken
    {
        if (blank($plain) || ! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        $token = ApiToken::with('user')->where('token_hash', self::hash($plain))->first();
        if ($token === null || ! $token->user?->active) {
            return null;
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $token;
    }

    private static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
