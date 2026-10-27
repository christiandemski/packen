<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Meldet einen Benutzer an und hält die Sitzung lange („Angemeldet bleiben“). */
class SessionLogin
{
    public const REMEMBER_MINUTES = 60 * 24 * 30;

    public function login(Request $request, User $user): void
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration(self::REMEMBER_MINUTES);
        }

        $guard->login($user, remember: true);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
